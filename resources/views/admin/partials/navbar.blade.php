@php
    $links = [];
    foreach ([['dashboard', 'Home', 'home'], ['admin.users.create', 'Create Account', 'add-user'], ['admin.students.import', 'Import Students', 'upload'], ['admin.users.index', 'Manage Accounts', 'users'], ['admin.classes', 'Manage Classes', 'classes']] as [$destination, $label, $icon]) {
        $active = request()->routeIs($destination)
            || ($destination === 'admin.users.index' && request()->routeIs('admin.users.delete'))
            || ($destination === 'admin.students.import' && request()->routeIs('admin.students.import.*'));
        $links[] = ['url' => route($destination), 'label' => $label, 'icon' => $icon, 'active' => $active];
    }
@endphp
<x-workspace-navigation role="admin" :links="$links" />
