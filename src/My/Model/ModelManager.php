<?php
/**
 * User: Alexander.Arutyunov
 * Date: 10.07.13
 * Time: 13:28
 */

namespace My\Model;

class ModelManager
{
    protected $_modelClass;

    public function __construct($modelClass)
    {
        $refl = new ReflectionClass($modelClass);
        $staticProps = $refl->getStaticProperties();
        //$modelFieldConfig = $refl->getStaticPropertyValue('_fieldConfig', null);
        //$dbTableName      = $refl->getStaticPropertyValue('_dbTableName', null);
        $modelFieldConfig = $staticProps['_fieldConfig'];
        $dbTableName      = $staticProps['_dbTableName'];
        $autoAddPk        = isset($staticProps['_autoAddPk'])?(bool)$staticProps['_autoAddPk']:true;
        if ($dbTableName !== null) {
            $this->_dbTableName = $dbTableName;
        }
        $this->_modelClass = $modelClass;
        $this->_setupFields($modelFieldConfig, $autoAddPk);
    }
}