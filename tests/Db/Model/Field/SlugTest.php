<?php

/**
 * Class SlugTest
 */
class SlugTest extends PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $q = UserModel::objects()->filter(['pk__lt' => 10])->limit(1);
        $obj = $q->current();
        $this->assertEquals($obj->slug, slugify($obj->fullname));
    }
}
 