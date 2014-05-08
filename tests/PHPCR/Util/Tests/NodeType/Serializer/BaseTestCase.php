<?php

namespace PHPCR\Util\Tests\NodeType\Serializer;

use Prophecy\Prophet;

class BaseTestCase extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->prophet = new Prophet();
    }

    protected function prophesize($name)
    {
        return $this->prophet->prophesize($name);
    }

    public function tearDown()
    {
        $this->prophet->checkPredictions();
    }
}
