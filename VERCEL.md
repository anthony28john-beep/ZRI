# Deploy ZeroRiskInitiative to Vercel

This project uses Vercel's container deployment with PHP 8.4 and FrankenPHP. The container builds Composer dependencies and Vite assets, then serves only Laravel's `public` directory. The configuration follows [Vercel's Laravel Docker guide](https://vercel.com/kb/guide/laravel-php-with-docker).

## Before deploying

You need:

- A Vercel account with container deployments available.
- The source repository connected to Vercel, or the Vercel CLI.
- Your existing Firebase project and its private service-account JSON.
- A managed Redis database with a Redis/TCP connection URL. Use its `rediss://` TLS URL, not an HTTPS REST API URL.
- A stable Laravel application key for each environment.

This configuration uses encrypted cookie sessions and Redis for shared cache and rate limits. It does not require a separate SQL database or SQL migrations. Firebase remains the source for accounts, profiles, classes, and game records. Local XAMPP configuration continues to support credential files and SQL sessions.

## 1. Upload the source

Commit the project source, including `Dockerfile.vercel`, `vercel.json`, the `docker` directory, and both dependency lockfiles. Import that repository into Vercel with the project root set to this folder.

The Docker build installs dependencies and builds frontend assets. Do not upload `node_modules`, `vendor`, or a prebuilt `public/build`. Do not configure a static `public` output directory or select the Vite static-site preset: the application must run through the container service declared in `vercel.json`.

`.dockerignore` and `.vercelignore` exclude local environment files, Firebase credential files, SQLite databases, and runtime storage. Check the files being committed before pushing. Keep all secrets in Vercel's environment settings, not Git.

## 2. Set environment variables

Open **Vercel Project Settings > Environment Variables**. Use [.env.vercel.example](.env.vercel.example) as a reference and replace its placeholders. The example is documentation; uploading it does not configure the project.

| Variable | Value |
| --- | --- |
| `APP_URL` | The full HTTPS address of your Vercel website |
| `APP_KEY` | A stable Laravel key; generate one locally with `php artisan key:generate --show` |
| `FIREBASE_CREDENTIALS_JSON` | The complete contents of your private Firebase service-account JSON, including its private key |
| `FIREBASE_PROJECT_ID` | The same Firebase project ID used by Unity |
| `FIREBASE_DATABASE_URL` | Your exact Realtime Database HTTPS URL |
| `REDIS_URL` | Your provider's `rediss://...` Redis connection URL, including authentication |
| `REDIS_PREFIX` and `CACHE_PREFIX` | Unique prefixes for this application and environment |

Paste `FIREBASE_CREDENTIALS_JSON` as the JSON object itself, without adding surrounding quotes or base64 encoding. Keep the private key's JSON `\n` escapes intact. The application reads it directly in memory; no credentials file is created inside `public` or uploaded into the image.

The Dockerfile supplies these production defaults. Keep them when configuring Vercel:

```dotenv
APP_ENV=production
APP_DEBUG=false
PORT=80
LOG_CHANNEL=stderr
LOG_LEVEL=warning
SESSION_DRIVER=cookie
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_DB=0
REDIS_CACHE_DB=0
QUEUE_CONNECTION=sync
TRUSTED_PROXIES=*
```

Do not use file or array sessions/cache in this deployment. Cookie sessions are encrypted and still subject to the application's live Firebase account checks and expiration rules. Redis keeps rate limits and cached results consistent across container instances. Both Redis connections use database 0 for providers that do not support additional logical databases.

Do not regenerate `APP_KEY` on each deployment. Use separate keys and Redis prefixes for Preview and Production. Prefer a separate Firebase project for previews; a preview using production Firebase can modify real accounts and classes.

The container checks required configuration at startup and reports missing variable names without printing their values. Do not run `php artisan config:cache` during the Docker build: secrets are provided at runtime.

## 3. Configure Firebase

In Firebase Authentication, add the deployed hostname to **Settings > Authorized domains**. Keep the Firebase-hosted verification and password-reset action pages, or configure another valid action handler deliberately.

The service account must have the same Authentication, Realtime Database, and Firestore permissions required locally. Do not loosen Firestore client rules to make the website work. Unity and the website must use the same project and player UID to share game records.

No Firebase data migration is needed for this deployment. Do not rerun administrator provisioning unless you intend to change an account's role.

## 4. Deploy

Deploy through the Vercel dashboard. Environment-variable changes require a new deployment.

Alternatively, with the Vercel CLI installed and the environment configured:

```powershell
vercel login
vercel link
vercel deploy
```

Test the preview before publishing production:

```powershell
vercel deploy --prod
```

## 5. Check the deployment

1. Visit `/up`; it should return HTTP 200. This confirms Laravel starts, not that Firebase and Redis permissions are correct.
2. Visit `/login` and check that styles, images, and navigation load over HTTPS.
3. Sign in with a test account and confirm the session survives refreshes and a redeployment with the same key.
4. Check verified and unverified accounts, temporary-password setup, and password recovery using test accounts.
5. Check scoreboards, teacher class permissions, and block applications against a staging Firebase project.
6. Confirm `/.env` and `/storage/app/firebase/firebase-credentials.json` do not return private file contents.

Application logs appear in Vercel runtime logs through stderr. There are no migrations or application workers in the container startup script. The current website performs its work during HTTP requests; container files are not used for durable uploads or records.

## Local container check

With Docker Desktop running:

```powershell
docker build -f Dockerfile.vercel -t zerorisk-vercel:local .
node tools/smoke-vercel.mjs
```

The smoke check uses synthetic credentials and an isolated Redis container. It checks health, login, assets, shared sessions, and throttling without signing into or changing a live Firebase project. It requires Node.js 22+ and may download `redis:7-alpine`.

## Troubleshooting

For `500 FUNCTION_INVOCATION_FAILED`, open the failed deployment's runtime Logs and reload its URL. This is a platform error; the screenshot alone does not identify its cause. If the logs contain `Vercel startup configuration is incomplete`, configure the named variables for the environment that produced that deployment (Production or Preview), then redeploy. Adding values only to Production does not configure a Preview deployment.

The container listens on port 80 by default, matching Vercel's default routing port. Keep `PORT=80` in Vercel's environment settings unless you deliberately configure another port. The server reads the same `PORT` variable. The image permits its non-root process to bind to port 80.

- **Startup configuration error:** Supply the missing environment variables and redeploy.
- **Redis connection error:** Use the provider's Redis/TCP TLS URL, verify its credentials, and allow connections from the deployment.
- **Login returns 419:** Check the stable `APP_KEY`, cookie settings, deployed HTTPS hostname, and proxy configuration.
- **Unstyled page:** Confirm the Docker service deployed and the frontend build completed. Remove static-site build/output overrides.
- **Firebase service unavailable:** Check the JSON environment value, project/database URLs, and service-account permissions.

Live Vercel deployment and Firebase connectivity must be verified with your own account settings; a successful local build does not configure those external services.

## Student CSV enrollment

Use **Import Students** in the admin menu for CSV files with `Fullname,email,block` (UTF-8, up to 100 rows and 1 MB). Preview and confirm before saving. Students activate using **New User** on the login page. Existing game accounts use normal login after their email is imported.

`APP_URL` must be the correct stable HTTPS production URL, and its hostname must be authorized in Firebase Authentication. Keep Firebase's hosted verification handler: after verifying, students select **Continue** to open the website password setup page. This flow uses the existing Firebase verification emails, not a separate SMTP account. Test this complete email round trip after deployment. Do not use a Preview hostname in production emails.

Redis stores short-lived CSV previews and reports across container instances. Invitations, activation-proof hashes, and one-time completion state are persisted in Realtime Database. Keep the `student_invitations` node inaccessible to public clients. The Unity registration UI and game-side enrollment enforcement must be updated in the separate Unity project.
