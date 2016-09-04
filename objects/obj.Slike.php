<?php

// Written by Dennis "reVerB" Rönnau
// This software is under the GNU-GPL v3 License
$SLikeEngine = new class() {
    private $CompiledPath = '';
    private $TemplatePath = '';
    private $SubTemplatePath = '';
    public $PageTitle = 'SLike says WELCOME';
    private $HTTPEquiv = ['charset' => 'utf-8', 'expires' => '0'];
    private $HTTPKeys = ['language', 'expires'];
    private $MetaTags = ['revisit-after' => '1 month'];
    private $MetaKeys = ['description', 'keywords', 'robots', 'author', 'date', 'publisher', 'copyright', 'page-topic', 'audience', 'revisit-after'];

    // Render and show headertags
    private function ShowHeaderTags() :string
    {
        $ReturnString = '<meta charset="'.$this->HTTPEquiv['charset']."\">\n";
        foreach ($this->HTTPEquiv as $Key => $Value) {
            if ($Key == 'charset') {
                continue;
            }
            $ReturnString .= '<meta http-equiv="'.$Key.'" content="'.$Value."\">\n";
        }
        foreach ($this->MetaTags as $Key => $Value) {
            $ReturnString .= '<meta name="'.$Key.'" content="'.$Value."\">\n";
        }

        return $ReturnString;
    }

    // Set the value for equiv
    public function SetHttpEquiv(string $Key, string $Value) :bool
    {
        if (in_array($Key, $this->HTTPKeys)) {
            $this->HTTPEquiv[$Key] = $Value;

            return true;
        }

        return false;
    }

    // Set a metatag value
    public function SetMetaTag(string $Key, string $Value) :bool
    {
        if (in_array($Key, $this->MetaKeys)) {
            $this->MetaTags[$Key] = $Value;

            return true;
        }

        return false;
    }

    // Setter
    public function __set(string $Variable, string $Value)
    {
        switch ($Variable) {
            case 'CompiledPath':
                $this->$Variable = file_exists($Value) ? $Value : '';
                break;
            case 'TemplatePath':
                $this->$Variable = file_exists($Value) ? $Value : '';
                break;
            case 'SubTemplatePath':
                $this->$Variable = file_exists($Value) ? $Value : '';
                break;
            default:
                break;
        }
    }

    // Clean the template
    private function RemovePHP(string $RawTemplate) :string
    {
        $RawTemplate = preg_replace('=<\?php(.*?)\?>=si', '', $RawTemplate);
        $RawTemplate = str_replace('<?php', '', $RawTemplate);
        $RawTemplate = str_replace('$', '&dollar;', $RawTemplate);

        return $RawTemplate;
    }

    // The compiling-method
    private function CompileTemplate(string $RawTemplate, array $Data, bool $IsTemplate = true) :string
    {
        // Search tags
        if (!empty($RawTemplate)) {
            preg_match_all('={if:(.*?)}=i', $RawTemplate, $ConditionMatches, PREG_SET_ORDER);
            preg_match_all('={loop:(.*?)}=si', $RawTemplate, $LoopMatches, PREG_SET_ORDER);
            preg_match_all('={data:(.*?)}=si', $RawTemplate, $DataMatches, PREG_SET_ORDER);
            if ($IsTemplate) {
                preg_match_all('={include:(.*?)}=si', $RawTemplate, $IncludeMatches, PREG_SET_ORDER);
            }
        } else {
            throw new Exception('SLike: The given template is empty');

            return '';
        }

        // Compiling Data's
        if (!empty($DataMatches)) {
            foreach ($DataMatches as $Match) {
                $RawTemplate = preg_replace_callback('={data:'.$Match[1].'}=i', function ($Hits) use (&$Data, &$Match) {
                    if (isset($Data[$Match[1]]) && (!is_bool($Data[$Match[1]]) || !is_array($Data[$Match[1]]))) {
                        return "<?php echo \$Data['".$Match[1]."']; ?>";
                    } else {
                        return 'SLIKE:MISSING_DATAENTRY';
                    }
                }, $RawTemplate, 1);
            }
        }


        // Compiling IF's
        if (!empty($ConditionMatches)) {
            foreach ($ConditionMatches as $Match) {
                $RawTemplate = preg_replace_callback('={if:'.$Match[1].'}(.*?){endif:'.$Match[1].'}=si', function ($Hits) use (&$Data, &$Match) {
                    if (isset($Data[$Match[1]]) && is_bool($Data[$Match[1]])) {
                        $ReturnPHP = "<?php if(\$Data['".$Match[1]."']==true) { ?>";
                        $ReturnPHP .= $Hits[1];
                        $ReturnPHP .= '<?php } ?>';

                        return $ReturnPHP;
                    } else {
                        return 'SLIKE:MISSING_CONDITION';
                    }
                }, $RawTemplate, 1);
            }
        }

        // Compiling Loop's
        if (!empty($LoopMatches)) {
            foreach ($LoopMatches as $Match) {
                $RawTemplate = preg_replace_callback('={loop:'.$Match[1].'}(.*?){endloop:'.$Match[1].'}=si', function ($Hits) use (&$Data, &$Match) {
                    if (isset($Data[$Match[1]]) && is_array($Data[$Match[1]])) {
                        preg_match_all('={'.$Match[1].':(.*?)}=si', $Hits[1], $DataMatches, PREG_SET_ORDER);
                        if (!empty($DataMatches)) {
                            foreach ($DataMatches as $index => $DataValues) {
                                $Hits[1] = preg_replace_callback('={'.$Match[1].':'.$DataValues[1].'}=si', function () use (&$Data, &$Match, &$DataValues, &$index) {
                                    $ReturnValue = (isset($Data[$Match[1]][$index][$DataValues[1]]) && !empty($Data[$Match[1]][$index][$DataValues[1]])) ? "<?php echo \$LoopData['".$DataValues[1]."'] ?>" : 'SLIKE:MISSING_LOOPDATA';

                                    return $ReturnValue;
                                }, $Hits[1], 1);
                            }
                            unset($Counter);
                            $ReturnPHP = "<?php foreach(\$Data['".$Match[1]."'] as \$LoopData) { ?>";
                            $ReturnPHP .= $Hits[1];
                            $ReturnPHP .= '<?php } ?>';

                            return $ReturnPHP;
                        }
                    } else {
                        return 'SLIKE:MISSING_LOOPARRAY';
                    }
                }, $RawTemplate, 1);
            }
        }

        // Compiling Include's
        if ($IsTemplate) {
            if (!empty($IncludeMatches)) {
                foreach ($IncludeMatches as $Match) {
                    $RawTemplate = preg_replace_callback('={include:'.$Match[1].'}=si', function () use (&$Match) {
                        $IncludeData = explode('#', $Match[1]);
                        if (isset($IncludeData[1])) {
                            $IncludeFile = $IncludeData[0];
                            $ParameterString = '$Parameter = array(); ';
                            foreach (explode(';', $IncludeData[1]) as $Option) {
                                $Parameter = explode(',', $Option);
                                $ParameterString .= (count($Parameter) == 2) ? "\$Parameter['".$Parameter[0]."'] = '".$Parameter[1]."'; " : '';
                            }
                            unset($IncludeData);
                        } else {
                            $IncludeFile = $Match[1];
                            $ParameterString = '';
                        }
                        $IncludeFile = $IncludeFile.'.php';
                        if (file_exists($IncludeFile)) {
                            return '<?php '.$ParameterString."include '".$IncludeFile."'; ?>";
                        } else {
                            return 'SLIKE:MISSING_INCLUDEFILE';
                        }
                    }, $RawTemplate, 1);
                }
            }

            // Compiling Single-Tags
            $RawTemplate = preg_replace('={system:pagecontent}=i', '<?php echo $PageContent; ?>', $RawTemplate, 1);
            $RawTemplate = preg_replace('={system:header}=i', '<?php echo $this->ShowHeaderTags(); ?>', $RawTemplate, 1);
            $RawTemplate = preg_replace('={system:title}=i', '<?php echo $this->PageTitle; ?>', $RawTemplate, 1);
        }

        return $RawTemplate;
    }

    // Method for compiling and show maintemplates
    public function ShowTemplate(string $TemplateFile, string $PageContent, array $Data) :bool
    {
        $TempFile = $this->TemplatePath.$TemplateFile.'.html';
        $OutputFile = $this->CompiledPath.str_replace(['/', '\\'], '_', $TemplateFile).'.php';
        if (file_exists($TempFile)) {
            if (file_exists($OutputFile)) {
                if (filemtime($TempFile) <= filemtime($OutputFile)) {
                    include $OutputFile;

                    return true;
                }
            }
            $Template = $this->RemovePHP(file_get_contents($TempFile));
            $Template = $this->CompileTemplate($Template, $Data);
            if (file_put_contents($OutputFile, $Template)) {
                include $OutputFile;

                return true;
            }
            throw new Exception("SLIKE: The compiled template can't write to a file.");

            return false;
        }
        throw new Exception('SLIKE: The given templatefile not exists.');

        return false;
    }

    // Method for compiling and show subtemplates
    public function ShowSubTemplate(string $TemplateFile, string $PageContent, array $Data) :bool
    {
        $TempFile = $this->SubTemplatePath.$TemplateFile.'.html';
        $OutputFile = $this->CompiledPath.str_replace(['/', '\\'], '_', $TemplateFile).'.php';
        if (file_exists($TempFile)) {
            if (file_exists($OutputFile)) {
                if (filemtime($TempFile) <= filemtime($OutputFile)) {
                    include $OutputFile;

                    return true;
                }
            }
            $Template = $this->RemovePHP(file_get_contents($TempFile));
            $Template = $this->CompileTemplate($Template, $Data, false);
            if (file_put_contents($OutputFile, $Template)) {
                include $OutputFile;

                return true;
            }
            throw new Exception("SLIKE: The compiled template can't write to a file.");

            return false;
        }
        throw new Exception('SLIKE: The given templatefile not exists.');

        return false;
    }
}
