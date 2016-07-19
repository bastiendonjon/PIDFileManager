<?php

namespace BastienDonjon;

use Exception;

/**
 * Class PIDFileManager

 * Class prevent of overlap between multiple executions of the cron job.
 * This class use PID file.

 * Stop conditions :
 * - Signals (ex : SIGTERM)
 * - End of file
 * - "Exit" method.
 *
 * Usage in simple task :
 *
 * $elem = new PIDFileManager('myProcessName', storage_path());
 * $elem->start();
 *
 * Usage in daemon task :
 *
 * $elem = new PIDFileManager('myProcessName', storage_path());
 * $elem->start();
 *
 * while(true) {
 *      sleep(1)
 *      $elem->oneLoop();
 * }
 *
*@package Actibase\Webservices\Domain
 */
class PIDFileManager
{

    /**
     *
     */
    const LOCK_FILE_EXTENSION = '.pid';

    /**
     * @var null
     */
    private $location = null;

    /**
     * @var String
     */
    private $processName = null;

    /**
     * @var String
     */
    private $processNameHashKey = null;

    /**
     * @var Resource
     */
    private $lockFileResource = null;

    /**
     * @var array
     */
    private static $signals = [
        SIGINT,
        SIGTERM,
        SIGQUIT
    ];

    /**
     * @param      $processName
     * @param null $location
     *
     * @throws Exception
     */
    public function __construct($processName, $location = null)
    {
        $this->processName = $processName;
        $this->location    = $location;

        $this->installSignalsHandler();
        $this->installShutdownHandler();

        if (is_string($this->location) && !is_dir($this->location)) {
            throw new Exception('Location path is invalid');
        }

        $this->processNameHashKey = md5($this->processName);
    }

    /**
     * @throws Exception
     */
    public function start()
    {
        if (!$this->readLockFile() || $this->processExist()) {
            throw new Exception('The process is already in progress');
        }

        $this->storePid();
    }

    /**
     * Fin de la commande
     */
    private function end()
    {
        $this->releaseLockFile();
        $this->closeLockFile();
        $this->removeLockFile();
    }

    /**
     * @return string
     */
    private function getLockFilePath()
    {
        $sections = [
            $this->location,
            $this->getLockFileName()
        ];

        return implode(DIRECTORY_SEPARATOR, $sections);
    }

    /**
     * @return string
     */
    private function getLockFileName()
    {
        return $this->processNameHashKey . self::LOCK_FILE_EXTENSION;
    }

    /**
     *
     */
    private function readLockFile()
    {
        $path                   = $this->getLockFilePath();
        $this->lockFileResource = fopen($path, 'a+');
        return flock($this->lockFileResource, LOCK_EX | LOCK_NB);
    }

    /**
     * @param $content
     */
    private function write($content)
    {
        fwrite($this->lockFileResource, $content);
    }

    /**
     *
     */
    private function storePid()
    {
        $this->clearLockFile();
        $this->write(getmypid());
    }

    /**
     * @return int|null
     */
    private function getPidLockFile()
    {
        $line = fgets($this->lockFileResource);
        return (is_bool($line)) ? null : (int) $line;
    }

    /**
     * @return bool
     */
    private function processExist()
    {
        $pid = $this->getPidLockFile();

        if ($pid === null) {
            return false;
        }

        return file_exists("/proc/$pid");
    }

    /**
     * @return bool
     */
    private function clearLockFile()
    {
        return ftruncate($this->lockFileResource, 0);
    }

    /**
     * @return bool
     */
    private function removeLockFile()
    {
        $path = $this->getLockFilePath();
        return @unlink($path);
    }

    /**
     * @return bool
     */
    private function closeLockFile()
    {
        return fclose($this->lockFileResource);
    }

    /**
     * @return bool
     */
    private function releaseLockFile()
    {
        return flock($this->lockFileResource, LOCK_UN);
    }

    /**
     * Check signals waiting to each loop for daemon.
     * This method should therefore be placed at the end of the loop.
     *
     * @link http://php.net/manual/fr/function.pcntl-signal-dispatch.php#92812
     */
    public function oneTime()
    {
        return pcntl_signal_dispatch();
    }

    /**
     * @param $signo
     */
    private function signalHandler($signo)
    {
        exit;
    }

    /**
     * Signals manager
     */
    private function installSignalsHandler()
    {
        declare(ticks = 1);
        foreach (self::$signals as $signal) {
            pcntl_signal(
                $signal,
                function ($signo) {
                    call_user_func([ $this, 'signalHandler'], $signo);
                }
            );
        }
    }

    /**
     *
     */
    private function installShutdownHandler()
    {
        register_shutdown_function(
            function () {
                call_user_func([ $this, 'end' ]);
                exit;
            }
        );
    }
}
