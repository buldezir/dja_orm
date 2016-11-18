<?php

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

class Multilang extends Virtual
{
    public function __construct(array $options = [])
    {
        $this->_options['lang_default'] = null;
        $this->_options['lang'] = [];
        $this->_options['inner_field_options'] = [
            Char::class,
            'default' => '',
            'blank' => true
        ];

        parent::__construct($options);
    }

    public function init()
    {
        if (!$this->getter) {
            $this->setOption('getter', function (\Dja\Db\Model\Model $model) {
                return $model->__get($this->lang_default);
            });
        }

        if (!$this->lang || !is_array($this->lang) || !count($this->lang)) {
            throw new \Exception('Must provide "lang" array');
        }

        if (!$this->lang_default) {
            $this->setOption('lang_default', $this->lang[0]);
        }

        foreach ($this->lang as $lang) {
            if (!preg_match('#[a-z]+#', $lang)) {
                throw new \Exception('"lang" must contain only [a-z] characters');
            }
            $this->metadata->addField($this->name . '_' . $lang, $this->inner_field_options);
        }

        parent::init();
    }

    public function getPhpType()
    {
        return 'string';
    }
}