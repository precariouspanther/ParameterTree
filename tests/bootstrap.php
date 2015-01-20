<?php
/**
 *
 * @author Adam Benson <adam@precariouspanther.net>
 * @copyright Arcanum Logic
 */
$autoloadPath = realpath(dirname(__FILE__) . '/../vendor/autoload.php');
if(!is_readable($autoloadPath)){
    throw new \Exception("Can't read composer /vendor/autoload.php. Have you run composer install?");
}

require($autoloadPath);