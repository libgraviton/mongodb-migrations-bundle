<?php
/**
 * graviton:mongodb:migrations:execute command
 */

namespace Graviton\MigrationBundle\Command;

use AntiMattr\MongoDB\Migrations\Configuration\ConfigurationBuilder;
use AntiMattr\MongoDB\Migrations\Migration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Finder\Finder;
use Graviton\MigrationBundle\Command\Helper\DocumentManager as DocumentManagerHelper;
use AntiMattr\MongoDB\Migrations\OutputWriter;
use Symfony\Component\Yaml\Yaml;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class MongodbMigrateCommand extends Command
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var DocumentManagerHelper
     */
    private $documentManager;

    /**
     * @var string
     */
    private $databaseName;

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @param ContainerInterface    $container       container instance for injecting into aware migrations
     * @param Finder                $finder          finder that finds configs
     * @param DocumentManagerHelper $documentManager dm helper to get access to db in command
     * @param string                $databaseName    name of database where data is found in
     */
    public function __construct(
        ContainerInterface $container,
        Finder $finder,
        DocumentManagerHelper $documentManager,
        $databaseName
    ) {
        $this->container = $container;
        $this->finder = $finder;
        $this->documentManager = $documentManager;
        $this->databaseName = $databaseName;

        parent::__construct();
    }

    /**
     * setup command
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('graviton:mongodb:migrate');
    }

    /**
     * call execute on found commands
     *
     * @param InputInterface  $input  user input
     * @param OutputInterface $output command output
     *
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        // one level above vendor
        $baseDir = __DIR__.'/../../../../../';

        $this->finder
            ->in($baseDir)
            ->path('Resources/config')
            ->name('/migrations.(xml|yml)/')
            ->files();

        foreach ($this->finder as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $output->writeln('Found '.$file->getRelativePathname());

            try {
                $this->runMigration($file->getPathname(), $output);
            } catch (\Exception $e) {
                $this->errors[] = sprintf(
                    'Error in migrations from "%s" (message "%s")',
                    $file->getRelativePathname(),
                    $e->getMessage()
                );
            }
        }

        if (!empty($this->errors)) {
            $output->writeln(
                sprintf('<error>%s</error>', implode(PHP_EOL, $this->errors))
            );
            return -1;
        }

        return 0;
    }

    /**
     * runs a migration based on the file
     *
     * @param string $filepath path to configuration file
     * @param Output $output   ouput interface need by config parser to do stuff
     *
     * @return AntiMattr\MongoDB\Migrations\Configuration\Configuration
     */
    private function runMigration($filepath, $output)
    {
        $outputWriter = new OutputWriter(
            function ($message) use ($output) {
                return $output->writeln($message);
            }
        );

        // write missing details to yml file and save to tmp
        $migrationData = Yaml::parseFile($filepath);
        $migrationData['database'] = $this->databaseName;

        // save to temp file
        $ymlConfigurationPath = tempnam(sys_get_temp_dir(), 'mig').'.yml';
        file_put_contents(
            $ymlConfigurationPath,
            Yaml::dump($migrationData)
        );

        $configurationBuilder = ConfigurationBuilder::create();
        $configurationBuilder->setOnDiskConfiguration($ymlConfigurationPath);
        $configurationBuilder->setOutputWriter($outputWriter);
        $configurationBuilder->setConnection($this->documentManager->getDocumentManager()->getClient());

        $migration = new Migration($configurationBuilder->build());
        $migration->migrate();
    }
}
