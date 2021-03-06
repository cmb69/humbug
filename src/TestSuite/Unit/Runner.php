<?php

/**
 * Class collecting all mutants and their results.
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/humbug/blob/master/LICENSE New BSD License
 * @author     Thibaud Fabre
 */
namespace Humbug\TestSuite\Unit;

use Humbug\Adapter\AdapterAbstract;
use Humbug\Container;
use Humbug\ProcessRunner;
use Symfony\Component\Process\PhpProcess;

class Runner
{
    /**
     * @var AdapterAbstract
     */
    private $adapter;

    /**
     * @var PhpProcess
     */
    private $process;

    /**
     * @var string
     */
    private $coverageLogFile;

    /**
     * @var Observer[]
     */
    private $observers = [];

    /**
     * @param AdapterAbstract $adapter
     * @param PhpProcess $process
     * @param $coverageLogFile
     */
    public function __construct(AdapterAbstract $adapter, PhpProcess $process, $coverageLogFile)
    {
        $this->adapter = $adapter;
        $this->coverageLogFile = $coverageLogFile;
        $this->process = $process;
    }

    /**
     * Adds an observer to the current test suite run.
     * @param Observer $observer
     */
    public function addObserver(Observer $observer)
    {
        $this->observers[] = $observer;
    }

    /**
     * Runs the current test suite.
     * @param Container $container
     *
     * @return Result
     */
    public function run(Container $container)
    {
        $this->onStart();

        $hasFailure = $this->performInitialTestsRun($this->process, $this->adapter);
        $coverage = null;
        $lineCoverage = 0;

        if ($this->adapter->ok($this->process->getOutput()) && $this->process->getExitCode() === 0) {
            /**
             * Capture headline line coverage %.
             * Get code coverage data so we can determine which test suites or
             * or specifications need to be run for each mutation.
             */
            $coverage = $this->adapter->getCoverageData($container);
            $lineCoverage = $coverage->getLineCoverageFrom($this->coverageLogFile);
        }

        $result = new Result($this->process, $hasFailure, $coverage, $lineCoverage);

        $this->onStop($result);

        return $result;
    }

    private function performInitialTestsRun(
        PhpProcess $process,
        AdapterAbstract $testFrameworkAdapter
    ) {
        $observers = $this->observers;
        $onProgressCallback = function ($count) use ($observers) {
            foreach ($observers as $observer) {
                $observer->onProgress($count);
            }
        };

        return (new ProcessRunner())->run($process, $testFrameworkAdapter, $onProgressCallback);
    }

    private function onStart()
    {
        foreach ($this->observers as $observer) {
            $observer->onStartSuite();
        }
    }

    private function onStop(Result $result)
    {
        foreach ($this->observers as $observer) {
            $observer->onStopSuite($result);
        }
    }
}
