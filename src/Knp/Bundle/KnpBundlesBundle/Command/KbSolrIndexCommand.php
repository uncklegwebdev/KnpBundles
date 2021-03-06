<?php

namespace Knp\Bundle\KnpBundlesBundle\Command;

use Knp\Bundle\KnpBundlesBundle\Entity\Bundle;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

use Knp\Bundle\KnpBundlesBundle\Indexer\SolrIndexer;

/**
 * @author Paweł Jędrzejewski <pjedrzejewski@diweb.pl>
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class KbSolrIndexCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('kb:solr:index')
            ->setDefinition(array(
                new InputOption('force', null, InputOption::VALUE_NONE, 'Force a re-indexing of all bundles'),
                new InputOption('skip-server-check', null, InputOption::VALUE_NONE, 'Skips check that Solr server is up and running.'),
                new InputArgument('bundleName', InputArgument::OPTIONAL, 'Bundle name to index'),
            ))
            ->setDescription('Indexes bundles in Solr')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('skip-server-check') && !$this->getContainer()->get('knp_bundles.utils.solr')->isSolrRunning()) {
            $output->writeln('<error>Solr is NOT running. Please start server first!</error>');

            return 1;
        }

        $verbose = $input->getOption('verbose');
        $force = $input->getOption('force');
        $bundleName = $input->getArgument('bundleName');

        $doctrine = $this->getContainer()->get('doctrine');
        /** @var $indexer \Knp\Bundle\KnpBundlesBundle\Indexer\SolrIndexer */
        $indexer = $this->getContainer()->get('knp_bundles.indexer.solr');

        if ($verbose) {
            $output->writeln($bundleName ? 'Getting bundle ('.$bundleName.') data to re-index.' : 'Getting bundles data to re-index.');
        }
        if ($bundleName) {
            list($ownerName, $name) = explode('/', $bundleName);
            $bundles = array($doctrine->getRepository('Knp\\Bundle\\KnpBundlesBundle\\Entity\\Bundle')->findOneByOwnerNameAndName($ownerName, $name));
        } elseif ($force) {
            $bundles = $doctrine->getRepository('Knp\\Bundle\\KnpBundlesBundle\\Entity\\Bundle')->findAll();
        } else {
            $bundles = $doctrine->getRepository('Knp\\Bundle\\KnpBundlesBundle\\Entity\\Bundle')->getStaleBundlesForIndexing();
        }

        if ($force) {
            if ($verbose) {
                $output->writeln('Deleting existing index.');
            }

            $indexer->deleteBundlesIndexes($bundleName ? current($bundles) : null);
        }

        /* @var $bundle Bundle */
        foreach ($bundles as $key => $bundle) {
            $this->reindex($bundle, $indexer, $output, $verbose);

            unset($bundles[$key]);
        }

        $doctrine->getManager()->flush();

        return 0;
    }

    /**
     * @param Bundle          $bundle
     * @param SolrIndexer     $indexer
     * @param OutputInterface $output
     * @param boolean         $verbose
     */
    private function reindex(Bundle $bundle, $indexer, $output, $verbose)
    {
        if ($verbose) {
            $output->writeln('Indexing '.$bundle->getFullName().'...');
        }

        try {
            $indexer->indexBundle($bundle);
        } catch (\Exception $e) {
            $output->writeln('<error>Exception: '.$e->getMessage().', skipping bundle '.$bundle->getFullName().'.</error>');
        }
    }
}
