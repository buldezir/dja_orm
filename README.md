Dja - simple way of doing 80% routine work
============================================
Best things from Django on our favourite php

Install
-------
With composer
```json
{
    "require": {
        "buldezir/dja_orm": "dev-master"
    }
}        
```
    php composer.phar install

Usage
-----
Create models manual
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
```
Or from database structure
```php
$dbConn = \Doctrine\DBAL\DriverManager::getConnection(array(
    'driver' => 'pdo_pgsql',
    'dbname' => '',
    'user' => '',
    'password' => '',
    'host' => 'localhost',
));
$dbi = new Dja\Db\Introspection($dbConn, $dbConn->getSchemaManager()->listTableNames());
$dbi->setPrefix('Model');

$dbi->processQueueCallback(function ($tableName, $modelClassName, $code) {
    file_put_contents('models.php', $code, FILE_APPEND);
});
```

Lookup
```php
// single object
$user1 = User::objects()->get(1);

// queryset
$allUsers = User::objects()->all();

// queryset with auto join and filters
$activeUsersWithRoles = User::objects()->selectRelated(['depth' => 3])->filter(['is_active' => 1, 'user_id__in' => [1,2,3,4,5]]);

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

// get result as key=>val list
$dict = User::objects()->filter(['is_active' => true])->valuesList('name', 'user_id');
foreach($dict as $userId => $userName){}

// raw query 
$iteratorOverModels = User::objects()->raw('SELECT * FROM users');
$iteratorOverArrays = User::objects()->raw('SELECT * FROM users')->returnValues();

// iterate fetching $chunkSize chunks
$chunkSize = 1000;
foreach (chunkedIterator($allUsers, $chunkSize) as $k => $v) {
    // for example if we have 5000 users there will be 5 db queries, in this foreach
}
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

// testhooks
