<?php

/**
 * Class for testing Web2project\Output\Email\Template functionality
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to Clear BSD License. Please see the
 *   LICENSE file in root of site for further details
 *
 * @author      Keith Casey <contrib@caseysoftware.com>
 * @category    Email
 * @package     web2project
 * @subpackage  unit_tests
 * @license     Clear BSD
 * @link        http://www.web2project.net
 */

// NOTE: This path is relative to Phing's build.xml, not this test.
include_once 'unit_tests/CommonSetup.php';

class Web2project_Output_Email_ManagerTest extends CommonSetup
{
    protected $manager = null;

    protected function setUp()
    {
        parent::setUp();

        $this->manager = new \Web2project\Output\Email\Manager();
    }

    public function testRender()
    {
        $object = new stdClass();
        $object->task_name = 'A task name';
        $object->company_id = '12345';
        $object->project_name = 'My Project';

        $raw_template = "My task is named {{task_name}} but my project is named {{project_name}}. By the way, they're owned by {{company_id}}.";
        $target_output = "My task is named A task name but my project is named My Project. By the way, they're owned by 12345.";

        $actual_output = $this->manager->render($raw_template, $object);

        $this->assertEquals($target_output, $actual_output);
    }
}