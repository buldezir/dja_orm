<?php

class ModelTest extends PHPUnit_Framework_TestCase
{
    public function testEmailValidation()
    {
        $m = new UserModel();
        $m->email = 'zzzzzzzz@ya.ru';
        $v = $m->validate();
        $this->assertCount(0, $v, validationErrorsToString($v));
    }
}