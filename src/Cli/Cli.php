<?php
/**
 * Command line helper to execute system commands.
 *
 * @since 2014-11-08
 * @author Dominik Siebel (dsiebel) <code@dsiebel.de>
 * @copyright (c) 2014 Dominik Siebel
 * @license MIT
 */

namespace Svn2Git\Cli;


class Cli {

    /**
     * Path to current working directory.
     * @var string
     */
    private $cwd = './';
    /**
     * Trust cli exit codes?
     * @var boolean
     */
    private $trustExitCodes = true;

    /**
     * Set current working dir.
     * @param string $path
     */
    public function setWorkingDir($path) {
        $this->cwd = $path;
    }

    /**
     * Sets whether to trust shell exit codes.
     * This also enables raising Exception if exit code != 0.
     * @param $trustExitCodes
     */
    public function setTrustExitCodes($trustExitCodes) {
        $this->trustExitCodes = (boolean) $trustExitCodes;
    }

    /**
     * Returns the configured current working directory.
     * Fallsback to <code>getcwd()</code> if not set.
     * @return string
     */
    public function getWorkingDir() {
        return isset($this->cwd)
            ? $this->cwd
            : getcwd();
    }

    /**
     * Execute a command via PHP's execute().
     * @param string $cmd Command to execute
     * @param string|null $ctx Path context to execute command in
     * @return array
     * @throws \RuntimeException If exit codes are trusted and exit code != 0
     */
    public function execute($cmd, $ctx = null) {
        $output = [];
        $return = 0;

        if ($ctx) {
            chdir($ctx);
        }

        exec($cmd . ' 2>&1', $output, $return);

        if ($ctx) {
            chdir($this->getWorkingDir());
        }

        if ($this->trustExitCodes && $return != 0) {
            throw new \RuntimeException("Error executing '$cmd' ($return):" . implode("\n", $output), $return);
        }

        return $output;
    }

    /**
     * Execute a shell command via PHP's passthru().
     * @param string $cmd Command to execute
     * @param string|null $ctx Path context to execute the command in
     * @throws \RuntimeException If exit codes are trusted and exit code != 0
     */
    public function passthru($cmd, $ctx = null) {
        $return = 0;

        if ($ctx) {
            chdir($ctx);
        }

        passthru($cmd, $return);

        if ($ctx) {
            chdir($this->getWorkingDir());
        }

        if ($this->trustExitCodes && $return != 0) {
            throw new \RuntimeException("Error executing '$cmd' ($return)");
        }
    }
}