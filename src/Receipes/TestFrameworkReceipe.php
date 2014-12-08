<?php
namespace Laravel\Installer\Receipe;

class TestFrameworkReceipe extends Receipe
{

    /**
     * Run the receipe updating $config
     *
     * @param &array $config
     * @return mixed
     */
    public function run(&$config)
    {
        $framework = $this->command->choice('Which testing framework do you use?', ['PHPUnit', 'PHPSpec']);

        switch ($framework) {
            case 'PHPSpec':
                $this->phpspecReceipe($config);
                break;
            case 'PHPUnit':
            default:
                // nothing to do as Laravel uses PHPUnit by default here
        }
    }

    /**
     * Ran when the user choose PHPSpec
     *
     * @param &array $config
     */
    protected function phpspecReceipe(&$config)
    {
        $this->updateConfig($config);
        $this->updateFiles($this->command->getDirectory());
    }

    /**
     * Remove PHPUnit from dependencies and add PHPSpec
     *
     * @param &array $config
     */
    protected function updateConfig(&$config)
    {
        $config['require-dev']['phpspec/phpspec'] = '2.*';
        unset($config['require-dev']['phpunit/phpunit']);
    }

    /**
     * Remove default PHPUnit files
     *
     * @param string $directory
     */
    protected function updateFiles($directory)
    {
        unlink($directory .'/tests/ExampleTest.php');
        unlink($directory .'/tests/TestCase.php');
        rmdir($directory .'/tests');
    }
}
