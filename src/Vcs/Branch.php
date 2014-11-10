<?php
/**
 * Branch Entity.
 *
 * @since 2014-11-10
 * @author Dominik Siebel (dsiebel) <code@dsiebel.de>
 * @copyright (c) 2014 Dominik Siebel
 * @license MIT
 */

namespace Svn2Git\Vcs;

class Branch {
    private $name;
    private $revision;
    private $message;

    public function __construct($name, $message = '', $revision = null) {
        $this->name = (string)$name;
        $this->message = (string)$message;
        $this->revision = $revision;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getRevision() {
        return $this->revision;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

}