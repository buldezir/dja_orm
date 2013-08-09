Dja - simple way of doing 80% routine work
============================================
Best things from Django on our favourite php
Usage
-----
```php
use Dja\Db\Model\Model;

class User extends Model
{
    protected static $fields = array(
        'user_id'       => array('Auto'),
        'email'         => array('Char'),
        'password'      => array('Char'),
        'full_name'     => array('Char', 'default' => ''),
        'date_added'    => array('DateTime', 'autoInsert' => true),
        'date_updated'  => array('DateTime', 'autoUpdate' => true),
        'is_active'     => array('Bool'),
        'timezone'      => array('Char'),
        'role'          => array('ForeignKey', 'relationClass' => 'Role'),
    );
}

class Role extends Model
{
    protected static $fields = array(
        'role_id'       => array('Auto'),
        'name'          => array('Char'),
        'is_active'     => array('Bool'),
        'can_do_smth1'  => array('Bool', 'default' => false),
        'can_do_smth2'  => array('Bool', 'default' => false),
        'can_do_smth3'  => array('Bool', 'default' => false),
    );
}

// single object
$user1 = User::objects()->get(1);

// queryset
$allUsers = User::objects();
$allUsers = User::objects()->all();

// queryset with auto join and filters
$activeUsersWithRoles = User::objects()->selectRelated()->filter(['is_active' => 1, 'user_id__in' => [1,2,3,4,5]]);

foreach ($activeUsersWithRoles as $user) {
    // Role object without quering db
    $role = $user->role;
    $role->is_active = 1;
    $role->save();
}

// auto backwards relation
$role1 = Role::objects()->get(1);
$roleUsers = $role1->users_set; // this is queryset
$roleUsersWithOrdering = $roleUsers->order('-full_name');

// add role to user by role
$role1->users_set->add(new User(), $user1);
// add role to user by user
$user1->role = $role1;
$user1->save();
```
