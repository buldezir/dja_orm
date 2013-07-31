<?php

namespace Dja\Auth;

use Dja\Db\Model\Model;

/**
 * Class User
 * @package App\Models
 *
 * @property int $user_id
 * @property int $role_id
 * @property string $email
 * @property string $password
 * @property string $full_name
 * @property string $date_added
 * @property string $date_updated
 * @property bool $is_active
 * @property string $timezone
 */
class User extends Model implements AuthUser
{
    protected static $fields = array(
        'user_id'       => array('Auto'),
        'email'         => array('Char'),
        'password'      => array('Char'),
        'full_name'    => array('Char', 'default' => ''),
        'role_id'       => array('Int', 'default' => 1),
        'date_added'    => array('DateTime', 'autoInsert' => true),
        'date_updated'  => array('DateTime', 'autoUpdate' => true),
        'is_active'     => array('Bool'),
        'timezone'      => array('Char'),
    );

    /**
     * @var string
     */
    protected static $dbtable = 'users';

    public function setEmail($value)
    {
        if ($this->inited && !preg_match('#^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$#i', $value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not valid email', $value));
        }
        $this->_set('email', $value);
    }

    /*public function setIsActive($value)
    {
        $this->data['is_active'] = (bool)$value;
    }*/

    /*public function setPassword($value)
    {
        dump(__METHOD__);
        $this->_data['password'] = sha1($value);
    }*/

    public function __toString()
    {
        return $this->full_name;
    }

    public function isAuthenticated()
    {
        return true;
    }

    public function isAnonymous()
    {
        return false;
    }

    public function isAllowed($context)
    {
        return Acl::isAllowed($context, $this->role_id);
    }
}

/*User::events()->addListener(User::EVENT_BEFORE_SAVE, function(\Symfony\Component\EventDispatcher\GenericEvent $event){
    dump(__FILE__.':'.__LINE__);
    $event->getSubject()->is_active = false;
    $event->stopPropagation();
});*/