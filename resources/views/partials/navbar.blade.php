@php
    $links = [];
    if ($portalRole === 'student') {
        foreach ([['dashboard', 'Home', 'home'], ['student.play-game', 'Download Game', 'download'], ['student.about-game', 'About the Game', 'book'], ['student.scoreboard', 'Scoreboard', 'trophy']] as [$destination, $label, $icon]) {
            $links[] = ['url' => route($destination), 'label' => $label, 'icon' => $icon, 'active' => request()->routeIs($destination)];
        }
    } else {
        foreach ([['home', 'Home', 'home'], ['students', 'Students', 'users'], ['progress', 'Progress', 'chart'], ['leaderboard', 'Leaderboard', 'trophy'], ['applications', 'Block Applications', 'inbox']] as [$section, $label, $icon]) {
            $links[] = ['url' => route('teacher.'.$section, request()->only('class')), 'label' => $label, 'icon' => $icon,
                'active' => request()->routeIs('teacher.'.$section, 'teacher.'.$section.'.*'),
                'count' => $section === 'applications' && isset($applications) ? count($applications) : 0];
        }
    }
@endphp
<x-workspace-navigation :role="$portalRole" :links="$links" />
