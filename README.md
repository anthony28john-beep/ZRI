<<<<<<< HEAD
# ZeroRiskInitiative

ZeroRiskInitiative is a school capstone project that connects a Unity safety education game with a Laravel web portal. Students can view their game results, teachers can monitor their classes, and administrators can manage accounts, blocks, and teacher assignments.

The website uses a maroon and white theme with dedicated administrator, teacher, and student workspaces.

## Technology stack

- Laravel 12 and PHP 8.2+
- Blade, CSS, JavaScript, and Vite
- Firebase Authentication for accounts and verification
- Firebase Realtime Database for profiles, blocks, and class assignments
- Cloud Firestore for Unity game records
- SQLite or another Laravel-supported SQL database for sessions, cache, and queues

## Features

| Workspace | Features |
| --- | --- |
| Student | Home, About the Game, personal scoreboard, level leaderboards, account information, and block applications |
| Teacher | Class overview, student rosters, individual results, level progress, leaderboards, application review, and student removal |
| Administrator | Account creation and management, account deletion, block management, and teacher assignments |

Accounts support email verification, password recovery, and email/password updates. Administrator-created accounts require replacement of their temporary password.

The Unity game runs separately and saves results to Firebase. The website reads these results; the Play Game page is reserved for game access and does not currently embed a playable build.

## Requirements

- PHP 8.2+, Composer, and Laravel's required PHP extensions
- Node.js 22.12+ and npm
- A configured SQL database with its PDO extension
- A Firebase project with Email/Password Authentication, Realtime Database, and Cloud Firestore enabled
- A private Firebase service account with Authentication, Realtime Database, and Firestore read permissions

## Local setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Configure Firebase and the SQL database in `.env` before continuing. Create the configured database; for SQLite, ensure `database/database.sqlite` exists. Retain the existing `.env` and application key when using an installation that is already configured.

```powershell
php artisan migrate
npm ci
npm run build
php artisan serve
```

Open the URL printed by Artisan. For frontend development, run `npm run dev` in another terminal. Under XAMPP and Apache, point the document root to the project's `public` directory.

### Firebase configuration

Store the service-account JSON at `storage/app/firebase/firebase-credentials.json`, or configure an absolute path to a private location outside the project.

```dotenv
FIREBASE_CREDENTIALS=storage/app/firebase/firebase-credentials.json
FIREBASE_DATABASE_URL=https://YOUR-DATABASE-INSTANCE.REGION.firebasedatabase.app
FIREBASE_PROJECT_ID=your-project-id
FIRESTORE_DATABASE_ID=(default)
FIREBASE_SESSION_MAX_AGE=43200
SESSION_ENCRYPT=true
```

Use the exact Realtime Database URL shown in Firebase Console; some instances use `firebaseio.com`. Configure authorized domains and verification/password-reset email templates in Firebase Authentication. Firebase's hosted action pages process the emailed links.

The Unity game and website must use the same Firebase project and Authentication UID for each student's results to appear under their website account.

### Administrator access

Create an email/password account in Firebase Authentication, or use an existing account, then run:

```powershell
php artisan firebase:admin administrator@example.com
```

Sign in and complete email verification. The command assigns administrator access without accepting a password or marking the email verified.

## Accounts and verification

Website registration asks for a full name, email, password, and selected block. The block selection becomes an application requiring teacher approval.

Students registered in Unity can sign in with the same Firebase email and password. A successful login creates a missing website profile with the student role and the available full name. These students keep their existing password. Existing profiles and administrator-issued temporary-password requirements are preserved.

Unverified users see the email-verification page before accessing protected dashboards. The website checks Firebase Authentication for the current verification status. Changing an account's email clears its verification status.

Full names are used in greetings, account details, rosters, applications, and leaderboards. Supported fields are `fullname`, `fullName`, and `full_name`. The website also supports its full-name form value stored as `name`; a value matching the profile's username is ignored. Usernames and Authentication display names are not used as fallback labels. Missing names use neutral labels such as Student or Player.

## Blocks and classes

Administrators create blocks and assign teachers through Manage Classes. Approved block members appear automatically in the corresponding roster. Administrators can also assign student memberships directly.

- Students must leave their current block before applying to another.
- A student can have one pending application and can cancel it before choosing another block.
- Assigned teachers accept or decline applications through Block Applications.
- Teachers remove members through Students > Remove student after confirmation.
- Removing block membership removes the student from rosters based on that block. Individual class assignments are managed separately.
- Leaving or removal preserves the student's account and game records.

Teachers with several classes can switch between them using the class selector. Individual records and removal actions are limited to currently assigned classes. Ownership and membership are checked again when an action is submitted.

Blocks can be deleted only when they have no students, pending applications, or class assignments. Account and block deletion require confirmation. Deleting a teacher retains their classes for reassignment; deleting an account retains its Firestore game records.

## Game results

The website reads these Firestore paths:

```text
players/{uid}
players/{uid}/levels/{levelId}
```

The player document supplies overall `points` and `totalAttempts`. Level documents supply `highestScore`, `bestGrade`, `bestTimeSec`, `attempts`, `completed`, and `lastPlayed`.

Personal results belong to the signed-in student's UID. Leaderboards show full names and highest scores. Teachers can view class progress and rankings, alongside all-student rankings. Dates use Philippine Time (UTC+8).

Level dropdowns discover available Firestore level documents automatically. A level such as `level2` appears after the game saves its first level document, even if nobody in the selected class has played it. Relevant result and level lookups are cached for up to 60 seconds. Publishing a level inside Unity alone does not create a Firestore record.

Missing records and unavailable results have separate display states. The website does not write scores or reset them when a student verifies an email or joins a block. Saving results for unverified Unity players depends on the game's code and Firestore rules.

## Data structure

| Database | Path | Purpose |
| --- | --- | --- |
| Realtime Database | `users/{uid}` | Profile, role, block membership, and pending application |
| Realtime Database | `blocks/{blockId}` | Block names |
| Realtime Database | `classes/{classId}` | Class and teacher assignments |
| Realtime Database | `teacher_classes/{teacherUid}/{classId}` | Teacher class index |
| Firestore | `players/{uid}` | Player profile and overall statistics |
| Firestore | `players/{uid}/levels/{levelId}` | Per-level game records |

Membership transactions check current profiles and class ownership together. They snapshot the Realtime Database root and retry conflicts up to three times; this approach is intended for the capstone's modest dataset.

## Security

Firebase Authentication manages passwords. Laravel stores session identity and credential metadata without retaining Firebase ID or refresh tokens. Protected requests check account status, role, verification, and credential state. Disabled or deleted accounts and changed credentials invalidate website sessions.

Protected routes use `firebase.session`, role middleware, and `account.ready` where verification and account setup are required. Account settings remain accessible before verification so users can correct their email.

Keep `.env`, the application key, databases, and service-account credentials private. Serve only the `public` directory. Passwords and sensitive SDK response bodies are excluded from application logs and validation flash data.

Realtime Database rules are stored in `firebase/database.rules.json`. Client writes to website profiles are denied; server operations use the Admin SDK after Laravel authorization. Firestore game-client rules are managed separately. Review and test rules before deploying them to a project containing game data.

With the Firebase CLI installed:

```powershell
firebase deploy --only database --project YOUR_PROJECT_ID
```

For website profile recovery and reconciliation:

```powershell
php artisan firebase:reconcile FIREBASE_UID
```

## Development checks

```powershell
php artisan test
php vendor/bin/pint --test
composer validate --no-check-publish
npm run build
```

Automated tests use mocked Firebase services and isolated session/cache stores. They do not require live credentials, send verification emails, or modify live accounts. Test security rules and the complete Unity-to-website flow in a separate staging project.

## Deployment

For Vercel, use the included container configuration and follow [VERCEL.md](VERCEL.md). It uses encrypted cookie sessions, Redis, and private Firebase environment credentials. The SQL-based commands below apply to conventional PHP hosting.

Use a PHP-capable hosting environment with production dependencies, built frontend assets, and persistent session/cache storage. Multiple instances must share those stores.

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the HTTPS address, `SESSION_ENCRYPT=true`, and `SESSION_SECURE_COOKIE=true`. Configure Firebase credentials and the SQL connection privately on the server.

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ensure storage and cache directories have the required permissions and remove the Vite development `public/hot` file before production use. Back up SQL and Firebase data and monitor application logs. Prepare platform-specific configuration for the selected hosting provider.

## References

- [Laravel documentation](https://laravel.com/docs/12.x)
- [Firebase Authentication](https://firebase.google.com/docs/auth)
- [Firebase Admin SDK for PHP](https://firebase-php.readthedocs.io/)
- [Firestore REST API](https://firebase.google.com/docs/firestore/use-rest-api)

=======
# ZRI
ZeroRiskInitiative
>>>>>>> e9123aac9def85940125a85e716d669e7a95d5dc
