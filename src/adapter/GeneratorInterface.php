<?php

namespace crudgen\adapter;

interface GeneratorInterface
{
    /**
     * setTargetDir
     *
     * @param  string $dir target directory
     * @return bool true if processing without error, false otherwise
     */
    public function setTargetDir($dir);

    /**
     * setConfig
     *
     * @param array $config configuration
     * @return bool true if processing without error, false otherwise
     */
    public function setConfig(array $config);

    /**
     * checkTemplateDir
     *
     * @return bool true if processing without error, false otherwise
     */
    public function checkTemplateDir();

    /**
     * init
     * @return bool true if processing without error, false otherwise
     */
    public function init();

    /**
     * Processor
     * @return bool true if processing without error, false otherwise
     */
    public function run();
}
