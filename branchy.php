<?php

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

class SvnFlowExecution {
    
    protected $working_directory = null;
    protected $action_name = null;
    protected $parameters = null;
    
    public function  __construct($working_directory, $parameters)
    {
        $this->working_directory = $working_directory . '/';
        $this->action_name = $parameters[1]; 
        $this->parameters = $parameters;
    }
    
    public function execute()
    {
        chdir($this->working_directory);
                
        if ($this->action_name === 'start-feature')
        {
            if (!isset($this->parameters[2]))
            {
                $this->logDebug('Correct usage:');
                $this->logDebug('* Starting a new feature: branchy start-feature feature_name');
                exit(1);
            }
            return $this->executeStartFeature($this->parameters[2]);   
        }
        if ($this->action_name === 'checkout')
        {
            if (!isset($this->parameters[2]))
            {
                $this->logDebug('Correct usage:');
                $this->logDebug('* Checking out a feature: branchy checkout feature_name');
                $this->logDebug('* Checking out trunk: branchy checkout trunk');
                exit(1);
            }
            return $this->executeCheckout($this->parameters[2]);   
        }
        if ($this->action_name === 'finish-feature')
        {
            return $this->executeFinishFeature();   
        }
        if ($this->action_name === 'features')
        {
            return $this->executeFeatures();   
        }        
        if ($this->action_name === 'show-changes')
        {
            return $this->executeShowChanges();   
        }          
        if ($this->action_name === 'merge-trunk')
        {
            return $this->executeMergeTrunk();   
        }        
        if ($this->action_name === 'auto-merge-trunk')
        {
            return $this->executeAutoMergeTrunk();   
        }           
    }
    
    public function executeStartFeature($feature_name)
    {
        $svn_data = $this->getSvnData();
        
        $repository_url = $svn_data['repository_url'];
        
        $start_url = $svn_data['repository_url'] . '/trunk';
        $target_url = $svn_data['repository_url'] . '/branches/' . $feature_name;
        
        
        system('svn cp --non-interactive -m\'Creating Feature Branch\' -- ' . $start_url . ' ' . $target_url);
        
        $this->logDebug('Created Feature Branch: ' . $feature_name);
        
        return $this->executeCheckout($feature_name);
    }

    public function executeCheckout($feature_name)
    {
        if (!$this->isWorkingCopyClean())
        {
            $this->logDebug('Workingcopy is modified. Aborting!');
            exit(1);
        }
        
        $svn_data = $this->getSvnData();
        
        if ($feature_name !== 'trunk')
        {
            $target_url = $svn_data['repository_url'] . '/branches/' . $feature_name;
        }
        else
        {
            $target_url = $svn_data['repository_url'] . '/trunk/';
        }
                
        system('svn switch --non-interactive ' .  $target_url . ' .');
        
        $this->logDebug('Checked out: ' . $feature_name);
    }
    
    public function executeFinishFeature()
    {
        if (!$this->isWorkingCopyClean())
        {
            $this->logDebug('Workingcopy is modified. Aborting!');
            exit(1);
        }
        
        try
        {
            $feature_name = $this->getActiveFeatureName();        
        }
        catch (Exception $exception)
        {
            $this->logDebug('You are on trunk at the moment. Nothing to finish!');
            exit(1);
        }
                
        $svn_data = $this->getSvnData();
        
        $repository_url = $svn_data['repository_url'];
        
        $start_url = $svn_data['repository_url'] . '/trunk';
        $target_url = $svn_data['repository_url'] . '/branches/' . $feature_name;
        
        system('svn switch --non-interactive ' .  $start_url . ' .');
        
        $dry_run_result = system('svn merge --reintegrate --dry-run -- ' .  $target_url);
        
        if (strpos($dry_run_result, ': '))
        {
            /*
             * Ok it may contain a "Text conflicts: 1" text, tell them to merge on their own!
             */
            $this->logDebug('');
            $this->logDebug('Automerge failed. Do (on your own):');
            $this->logDebug('  svn merge --reintegrate  -- ' .  $target_url);
            $this->logDebug('As soon as you are done, do: svn ci');
            exit(1);
        }
                
        system('svn merge --reintegrate  -- ' .  $target_url);
        system('svn rm --non-interactive -m\'Closing Feature Branch\' -- ' . $target_url);
        system('svn ci -m\'Reintegrated branch \'' . $feature_name . ' .');
        
        $this->logDebug('Finished Feature: ' . $feature_name . ' and back on trunk.'); 
    }
    
    public function isWorkingCopyClean()
    {
        $raw_data = $this->executeSvnCommand('status .');
        
        if (isset($raw_data['target']['entry']))
        {
            return false;
        }
        
        return true;
    }

    public function executeMergeTrunk()
    {
        if (!$this->isWorkingCopyClean())
        {
            $this->logDebug('Workingcopy is modified. Aborting!');
            exit(1);
        }
        
        $svn_data = $this->getSvnData();
        
        $start_url = $svn_data['repository_url'] . '/trunk';
                
        $dry_run_result = system('svn merge --dry-run -- ' . $start_url);
        
        if (strpos($dry_run_result, ': '))
        {
            /*
             * Ok it may contain a "Text conflicts: 1" text, tell them to merge on their own!
             */
            $this->logDebug('');
            $this->logDebug('Automerge failed. Do (on your own):');
            $this->logDebug('  svn merge ' . $start_url);
            $this->logDebug('As soon as you are done, do: svn ci');
            exit(1);
        }

        $had_changes = strlen(trim($dry_run_result)) ? true : false;
        
        if ($had_changes)
        {
            system('svn merge -- ' . $start_url);
            $this->logDebug('Merged changes from trunk.');        
        }
        else
        {
            $this->logDebug('Nothing to merge from trunk.');    
        }
        
        return $had_changes;
    }

    public function executeAutoMergeTrunk()
    {
        if (!$this->executeMergeTrunk())
        {
            $this->logDebug('Nothing changed. That\'s why no autocommit!');
            exit(1);
        }
                
        /*
         * Merge only if changes were made
         */
        system('svn ci --non-interactive -m\'Merged trunk\' .');
    }
    
    public function executeShowChanges()
    {
        /*
         * Muss auf alle Fälle beachten, wann der feature branch angefangen wurde.
         */
        $svn_data = $this->getSvnData();
        
        try
        {
            $feature_name = $this->getActiveFeatureName();        
        }
        catch (Exception $exception)
        {
            /*
             * On trunk ... , so just svn di!
             */
            system('svn di --non-interactive .');
            return ;
        }
        
        $start_url = $svn_data['repository_url'] . '/trunk';
        $target_url = $svn_data['repository_url'] . '/branches/' . $feature_name;
        
        system('svn di --non-interactive -- ' . $start_url . ' ' . $target_url);                
    }
            
    public function executeFeatures()
    {
        $raw_data = $this->executeSvnCommand('ls ' . $this->getRepositoryUrl() . '/branches/');

        $feature_name = null;
                
        try
        {
            $feature_name = $this->getActiveFeatureName();        
        }
        catch (Exception $exception)
        {
        }
                 
        if (!isset($raw_data['list']['entry']))
        {
            $this->logDebug("No Feature Branches running at the moment. Create one with: branchy start-feature feature_name");
            exit(0);
        }
        
        $this->logDebug("Features:");

        $entries = $raw_data['list']['entry'];
        
        if (!isset($entries[0]))
        {
            $entries = array($entries);
        }
        
        foreach ($entries as $entry)
        {
            echo $this->logDebug('[' . ($entry['name'] == $feature_name ? '*' : ' ') . '] ' . $entry['name']);
        }
        return ;
    }
    
    protected function logDebug($data)
    {
        echo $data . PHP_EOL;
    }
    
    protected function getActiveFeatureName()
    {
        $svn_data = $this->getSvnData();
        
        $repository_url = $svn_data['repository_url'];
        
        $raw_data = $this->executeSvnCommand('info .');
        
        $current_path_url = $raw_data['entry']['url'];
        
        $feature_name = null;
        
        if (substr($current_path_url, 0, strlen($repository_url . '/branches/')) == $repository_url . '/branches/')
        {
            /*
             * Ist ein branch
             */
            $feature_name = substr($current_path_url, strlen($repository_url . '/branches/'));
            if (strpos($feature_name, '/') > 0)
            {
                $feature_name = substr($feature_name, 0, strpos($feature_name, '/'));
            }
        }
        else
        {
            throw new Exception('You are not on a feature branch.');            
        }
        
        return $feature_name;        
    }
    
    protected function getRepositoryUrl()
    {        
        $svn_data = $this->getSvnData();        
        return $svn_data['repository_url'];
    }
    
    protected function executeSvnCommand($command)
    {
        $raw_data_text = shell_exec('svn --xml ' . $command);
        $raw_data_xml = simplexml_load_string($raw_data_text);
        return json_decode(json_encode($raw_data_xml), true);
    }
    
    protected function getSvnData()
    {
        $raw_data_text = shell_exec('svn info --xml ' . escapeshellarg('.'));
        $raw_data_xml = simplexml_load_string($raw_data_text);
        return array(
            'revision' => (string) $raw_data_xml->entry->attributes()->revision,
            'last_modified_date' => strtotime((string) $raw_data_xml->entry->commit->date),
            'repository_url' => (string) $raw_data_xml->entry->repository->root
        );      
    }

}

$execution = new SvnFlowExecution(getcwd(), $argv);
$execution->execute();
