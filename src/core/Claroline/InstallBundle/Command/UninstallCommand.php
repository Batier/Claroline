<?php

namespace Claroline\InstallBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;

class UninstallCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('claroline:uninstall')
             ->setDescription('uninstall the platform to a clear state.');
        
        $this->addOption(
            'with-plugins', 
            'w', 
            InputOption::VALUE_OPTIONAL, 
            "When set to true, available plugins will be installed", 
            false
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Launching uninstaller...');
        
        $delegateInput = new ArrayInput($input->getArguments());
        
        $options = $input->getOptions();
        if(isset($options['with-plugins']))
        {
            
                        
            $pluginRemover = $this->getApplication()->find('claroline:plugin:remove_all');
            $pluginRemover->run($delegateInput, $output);
        }
        
        $command = $this->getApplication()->find('claroline:security:drop_acl');        
        $command->run($delegateInput, $output);

        $manager = $this->getContainer()->get('claroline.install.core_installer');
        $manager->uninstall();
        
        
        
        $output->writeln('Done');
        
    }
    
    
}