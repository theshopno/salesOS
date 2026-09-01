<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_110
{
    public function up()
    {
        require dirname(__DIR__) . '/install.php';
    }

    public function down()
    {
        return true;
    }
}
