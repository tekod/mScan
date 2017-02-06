<?php

/**
 * mScan - directory scanner and reporter
 *
 * Copyright (c) 2016 Tekod labs.
 *  
 * Miroslav Ćurčić <office@tekod.com>
 *
 * @author Miroslav Ćurčić <office@tekod.com>
 * @copyright 2016 Tekod labs - http://www.tekod.com
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version 0.3.1
 */


class mScan {
    
    // version number
    protected $Version= '0.3.1';

    // locations where to perform scanning
    protected $PathsToScan= array(
        '/var/www/public_html',        
    );

    // file extension to process (empty array will scan all files)
    protected $Extensions= array('php', 'js', 'htm', 'html');

    // directories to ignore (full paths)
    protected $IgnoreDirs= array();
    
    // where to store file with list of last known files (basename or relative or FQFN)
    // it is recommended to place this out of HTTP access reach
    protected $StorageLocation= 'mScan.dat';

    // sending email with scanning result
    protected $EmailReport= array(
        'Enabled'=> false,
        'ToAddress'=> 'myaddress@myemailprovider.tld',
        'FromAddress'=> 'mscan@mysite.tld',
        'Subject'=> 'mScan report',    
    );
    
    // for files lighter then this value use internal PHP's hash function
    // hash for larger files will be calculated manualy
    protected $HashFileLimit= 2048000;
            
    // dictionary for output messages,
    // use this configuration to translate/customize output report
    protected $Messages= array(
        'New'=> 'New file added: %s {%s}',
        'Del'=> 'Deleted file: %s',
        'Mod'=> 'Modified file: %s {%s}',
        'DiffNotFound'=> 'Nothing to report.',
        'DiffFound'=> 'Differences found:',
    );
    
    // template for output report
    // use this configuration to translate/customize output
    protected $ReportTemplate= '<h1>mScan <cite style="font-size:0.5em">ver. %s</cite></h1>'
            .'<hr><br>%s'
            .'<br><br><hr><small>Found %s files<br>Timestamp: %s'
            .'<br>Last scanning occured on %s</small><br><br>';


    // internal vars
    
    protected $IsExecDisabled;
    
    protected $IsCLI;

    protected $ExistingFiles;
    
    protected $KnownFiles;
    
    protected $Report;

    protected $LastTimestamp;



    public function __construct($Options=array()) {
                     
        // ensure relative paths works 
        chdir(dirname(__FILE__));
        if (strpos($this->StorageLocation, '/') === false) {
            $this->StorageLocation= __DIR__.'/'.$this->StorageLocation;
        }
        
        // check what we can use
        $DisabledFunctions= explode(',', ini_get('disable_functions'));
        $this->IsExecDisabled= in_array('exec', $DisabledFunctions);
        
        // is this CLI enviroment? ("cli", "cgi" or "cgi-fcgi")
        $this->IsCLI= defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) 
           and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv'])>0);
        
        // prevent echoing errors on CLI triggering, 
        // otherwise show them all (for debugging purposes)
        error_reporting($this->IsCLI ? 0 : E_ALL);
        
        // override default properties
        foreach($Options as $k => $v) {
            $this->$k= $v;
        }
        
    }
    
    
    public function Run() {
        
        // get list of exising files
        $this->GetExistingFiles();
        
        // get list of known files
        $this->GetKnownFiles();
        
        // compare lists and generate report
        $this->Compare();
        
        // save list of known files for latter usage
        $this->StoreKnownFiles();
        
        // output report
        $this->SendReport();
    }    
    

    protected function GetExistingFiles() {
        
        $this->ExistingFiles= array();
        foreach ($this->PathsToScan as $StartPath) {
            $this->ScanDirectory($StartPath, '');
        }
    }
    

    protected function ScanDirectory($StartPath, $Dir) {

        $Path= str_replace('\\','/',$StartPath).$Dir;  // concat path
        $Path= str_replace('//','/',$Path);            // remove double slashes
        
        foreach(scandir($Path, SCANDIR_SORT_NONE) as $Entry) { 
            $File= $Path.'/'.$Entry;
            if (is_dir($File)) {      
                if ($Entry === '.' || $Entry === '..' || in_array($File, $this->IgnoreDirs)) {
                    continue;
                }
                $this->ScanDirectory($StartPath, $Dir.'/'.$Entry);                    
            } else {    
                if (!is_file($File)) {
                    continue;
                }
                if (!empty($this->Extensions)) {
                    $Ext= explode('.', $Entry);
                    if (count($Ext) == 1 || !in_array(strtolower(end($Ext)), $this->Extensions)) {
                        continue;
                    }
                }
                $this->ExistingFiles[$File]= array(
                    $this->GetHash($File), 
                    filemtime($File),
                );        
            }
        }
    }
 
    
    protected function GetHash($Path) {
        
        // for files less then 2Mb (by default) use PHP's internal hash_file() ...
        if (filesize($Path) < $this->HashFileLimit) {
            return hash_file('md5', $Path);
        } 
        
        // if allowed to use function "exec" ...
        if (!$this->IsExecDisabled) {
            $Results= array();
            exec("md5sum $Path", $Results);
            $Hash= explode(' ',$Results[0]);
            return $Hash[0];
        }
        
        // fallback to manual calculation (pseudo md5)
        $f= fopen($Path, 'rb');
        $md5= '';
        while (!feof($f)) {
            $Chunk= fread($f, 1*1024*1024);  // read in chunks of 1Mb
            $md5 .= md5($Chunk, true);
        }
        fclose($f);
        return md5($md5);
    }
    
    
    protected function GetKnownFiles() {
        
    $Dump= file_get_contents($this->StorageLocation);
    
        if (is_file($this->StorageLocation)) {
            $Dump= @file_get_contents($this->StorageLocation);
            $Lines= explode("\n", gzinflate($Dump));
        } else {
            $Lines= array();
        }
        $this->KnownFiles= array();
        foreach($Lines as $Line) {
            $Parts= explode(',', trim($Line), 3);
            if (count($Parts) == 3) {
                $this->KnownFiles[$Parts[2]]= array($Parts[0], intval($Parts[1]));
            }
        }
        $this->LastTimestamp= filemtime($this->StorageLocation);
    }
    
    
    protected function StoreKnownFiles() {
        
        $Pack= array();
        foreach($this->ExistingFiles as $k => $v) {
            $Pack[]= $v[0].','.$v[1].','.$k;
        }
        file_put_contents($this->StorageLocation, gzdeflate(implode("\n", $Pack)));
    }
    
    
    protected function Compare() {
        
        $KnownFiles= array_keys($this->KnownFiles);
        $ExistingFiles= array_keys($this->ExistingFiles);
        $this->Report= array();
        
        // check for deleted files
        foreach(array_diff($KnownFiles, $ExistingFiles) as $File) {
            $this->Report[]= sprintf($this->Messages['Del'], $File);
        }
        
        // check for new files
        foreach(array_diff($ExistingFiles, $KnownFiles) as $File) {
            $Date= date('Y-m-d H:i:s', $this->ExistingFiles[$File][1]);
            $this->Report[]= sprintf($this->Messages['New'], $File, $Date);
        }
                
        // check for modified files, compare hashes and dates
        foreach(array_intersect($ExistingFiles, $KnownFiles) as $File) {
            if ($this->ExistingFiles[$File] === $this->KnownFiles[$File]) {
                continue;
            }
            $Date= date('Y-m-d H:i:s', $this->ExistingFiles[$File][1]);
            $this->Report[]= sprintf($this->Messages['Mod'], $File, $Date);
        }        
    }
    
    
    protected function SendReport() {
        
        // prepare LastTime
        $Delta= time()-$this->LastTimestamp;
        $Ago= round($Delta / 86400).' days ago';
        if ($Delta < 2*86400) $Ago= round($Delta / 3600).' hours ago';
        if ($Delta < 2*3600) $Ago= round($Delta / 60).' minutes ago';        
        $LastTimestamp= date('Y-m-d H:i:s', $this->LastTimestamp);
        $LastTime= $LastTimestamp.' ('.$Ago.')';
        
        // prepare Report
        $Content= empty($this->Report)
            ? $this->Messages['DiffNotFound']
            : $this->Messages['DiffFound'].'<br><br>'.implode("<br>",  $this->Report);
        $Count= count($this->ExistingFiles);
        $Now= date('Y-m-d H:i:s', time());
        $Ver= $this->Version;
        $Report= sprintf($this->ReportTemplate, $Ver, $Content, $Count, $Now, $LastTime);        
        
        // if emailing enabled and differences found 
        if ($this->EmailReport['Enabled'] && !empty($this->Report)) {
            // prepare headers
            $Subject= str_replace(array("\r","\n"), '', $this->EmailReport['Subject']);
            $ToAddress= str_replace(array("\r","\n"), '', $this->EmailReport['ToAddress']);            
            $FromAddress= str_replace(array("\r","\n"), '', $this->EmailReport['FromAddress']);
            $Headers= "From: $FromAddress\r\nReply-To: $FromAddress\r\nX-Mailer: PHP/".phpversion();  
            // de-HTML report
            $PlainReport= str_replace(array("\n","\r"), '', $Report); // remove newlines
            $PlainReport= str_replace("\t", ' ', $PlainReport); // convert tabs to spaces
            $PlainReport= preg_replace('/\s+/', ' ',$PlainReport); // compress multispaces
            $PlainReport= preg_replace('/\s*<br>\s*/', "\r\n", $PlainReport); // convert <br> to nl
            $PlainReport= str_replace('<hr>',"\r\n".str_repeat('-',65)."\r\n", $PlainReport); // <hr>               
            $PlainReport= trim(strip_tags($PlainReport));  // remove tags
            // send email
            mail($ToAddress, $Subject, $PlainReport, $Headers);            
        }
        
        // show report if not triggered from CLI         
        if (!$this->IsCLI) {
            echo $Report;
        } 
        
    }
    
}

?>