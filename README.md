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
        'user_id'       =>['Auto'],
        'email'         =>['Char'],
        'password'      =>['Char'],
        'full_name'     =>['Char', 'default' => ''],
        'date_added'    =>['DateTime', 'autoInsert' => true],
        'date_updated'  =>['DateTime', 'autoUpdate' => true],
        'is_active'     =>['Bool'],
        'timezone'      =>['Char'],
        'role'          =>['ForeignKey', 'relationClass' => 'Role'],
    );
}

class Role extends Model
{
    protected static $fields = array(
        'role_id'       =>['Auto'],
        'name'          =>['Char'],
        'is_active'     =>['Bool'],
        'can_do_smth1'  =>['Bool', 'default' => false],
        'can_do_smth2'  =>['Bool', 'default' => false],
        'can_do_smth3'  =>['Bool', 'default' => false],
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
**U can write your own fields**
```php
class JsonField extends BaseField
{
    public function cleanValue($value)
    {
        return is_array($value) ? $value : json_decode($value);
    }

    public function dbPrepValue($value)
    {
        return json_encode($value);
    }
    
    public function getDefault()
    {
        return [];
    }
}
class TestModel
{
    protected static $fields = array(
        ...
        'options' =>['JsonField'],
    );
}
$obj = new TestModel;
$obj->options['ololo'] = 'jjjjjj';
$obj->save(); // will store json string in text field
```
**And getters and setters of course**
```php
class TestModel2 extends TestModel
{
    ...
    public function setOptions($value)
    {
        if (!is_array($value)) {
            throw new \Exception('GTFO');
        }
        $this->_set('options', $value);
    }
    
    public function getOptions()
    {
        return array_merge($this->_get('options'), get_global_options());
    }
}
```
