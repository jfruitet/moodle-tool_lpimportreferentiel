<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the form add/update a competency framework.
 *
 * @package   tool_lpimportreferentiel
 * @copyright 2015 Damyon Wiese
 * @copyright 2016 Jean Fruitet jean.fruitet@free.fr 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lpimportreferentiel;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

use context_system;
use core_competency\api;
use core_competency\invalid_persistent_exception;
use DOMDocument;
use stdClass;
use grade_scale;


/**
 * Import Competency framework form.
 *
 * @package   tool_lpimportreferentiel
 * @copyright 2015 Damyon Wiese
 * @copyright 2016 Jean Fruitet jean.fruitet@free.fr 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_importer {

    /** @var string $error The errors message from reading the xml */
    var $error = '';

    /** @var array $tree The competencies tree */
    var $tree = array();

    /** @var stdClass The framework node */
    var $framework = null;
    
    /** @var stdClass The imported scale object */
    var $scalerec = null;
    
    var $scale_defaultindex = 0;
    var $scale_maxindex = 0;
    

    public function fail($msg) {
        $this->error = $msg;
        return false;
    }

    /**
     * Constructor - parses the raw xml for sanity.
     */
    public function __construct($xml) {
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml)) {
            $this->fail(get_string('invalidimportfile', 'tool_lpimportreferentiel'));
            return;
        }
        
        $this->framework = new stdClass();
        $this->framework->shortname = get_string('unnamed', 'tool_lpimportreferentiel');
        $this->framework->idnumber = generate_uuid();
        $this->framework->description = '';
        $this->framework->subject = '';
        $this->framework->educationLevel = '';

        // Verification
        $referentiel = $doc->getElementsByTagName('referentiel');
        if (!$referentiel)
        {
        	$this->fail(get_string('invalidimportfile', 'tool_lpimportreferentiel'));
        	return;
        }
        
        $records = array();
        
        $i=0;
        while(is_object($referentiels = $doc->getElementsByTagName("referentiel")->item($i)))
        {
        	foreach($referentiels->childNodes as $referentiel)
        	{        	
       		if ($referentiel->nodeName=='bareme')
        		{
        			$this->scalerec = new stdClass();
        			$this->scalerec->id = 0;
        			$this->scalerec->courseid = 0;
        			$this->scalerec->userid = 0;
        			$this->scalerec->name = '';
        			$this->scalerec->scale ='';
        			$this->scalerec->description = '';
        			$this->scalerec->descriptionformat = 0;
        			$this->scalerec->timemodified = time();
        			
        			// BAREME
        			foreach($referentiel->childNodes as $bareme)
        			{
        				if ($bareme->nodeName=='b_name'){        					
        					$this->scalerec->name = $bareme->nodeValue;
        				}
        				elseif ($bareme->nodeName=='b_desc'){
        					$this->scalerec->description = $bareme->nodeValue;
        				}
        				elseif ($bareme->nodeName=='b_scale'){
        					$this->scalerec->scale = $bareme->nodeValue;
        				}
        				elseif ($bareme->nodeName=='b_seuil'){
        					$this->scale_defaultindex = $bareme->nodeValue;
        				}
        				elseif ($bareme->nodeName=='b_max'){
        					$this->scale_maxindex = $bareme->nodeValue;
        				}        				
        			}
        		} 
        			
        		elseif ($referentiel->nodeName=='domaine')
        		{
        			//DOMAINE        		
        			foreach($referentiel->childNodes as $domaine)
        			{
        				if ($domaine->nodeName=='competence')
        				{
        					//COMPETENCE
        					foreach($domaine->childNodes as $competence)
        					{
        						if ($competence->nodeName=='item')
        						{
        							//Items
        							foreach($competence->childNodes as $item)
        							{
        								if ($item->nodeName=='code'){
        									$record = new stdClass();
        									$record->shortname = $item->nodeValue;
        									$record->idnumber = $item->nodeValue;
        									$record->betteridnumber = $item->nodeValue;
        								}
        								elseif ($item->nodeName=='description_item'){
        									$record->description = $item->nodeValue;
        									$record->parents = array($id_competence);
        									$record->children = array();
        									$record->childcount = 0;
        									array_push($records, $record);
        								}

        							}
        						}
        						else
        						{
        							if ($competence->nodeName=='code_competence'){
        								$record = new stdClass();
        								$record->shortname = $competence->nodeValue;
        								$record->idnumber = $competence->nodeValue;
        								$record->betteridnumber = $competence->nodeValue;
        								$id_competence=$competence->nodeValue;
        							}
        							elseif ($competence->nodeName=='description_competence'){
        								$record->description = $competence->nodeValue;
        								$record->parents = array($id_domaine);
        								$record->children = array();
        								$record->childcount = 0;
        								array_push($records, $record);
        							}
        							
        						}
        					}
        				}
        				else
        				{
        					//echo $domaine->nodeName." :: ".$domaine->nodeValue."<br>";
        					if ($domaine->nodeName=='code_domaine'){
        						$record = new stdClass();        						
        						$record->shortname = $domaine->nodeValue;
        						$record->idnumber = $domaine->nodeValue;
        						$record->betteridnumber = $domaine->nodeValue;
        						$id_domaine=$domaine->nodeValue;
        					}
        					elseif ($domaine->nodeName=='description_domaine'){
        						$record->description = $domaine->nodeValue;
        						$record->parents = array();
        						$record->children = array();
        						$record->childcount = 0;
        						array_push($records, $record);
        					}        					 
        				}
        			}
        		}  
        		else
        		{
        			// referentiel
        			if ($referentiel->nodeName=='code_referentiel'){
        				$this->framework->idnumber = $referentiel->nodeValue;
        				$this->framework->shortname = $this->framework->idnumber;
        			}
        			elseif ($referentiel->nodeName=='description_referentiel'){
        				$this->framework->description = $referentiel->nodeValue;
        			}
        			elseif ($referentiel->nodeName=='url_referentiel'){
        				$this->framework->subject =  $referentiel->nodeValue;
        			}
        		}
         	}
        	$i++;
        }
        

        if ($this->framework->subject) {
            $this->framework->description .= '<br/>' . get_string('subject', 'tool_lpimportreferentiel', $this->framework->subject);
        }
                        
        // Now rebuild into a tree.
        foreach ($records as $key => $record) {
            $record->foundparents = array();
            if (count($record->parents) > 0) {
                $foundparents = array();
                foreach ($records as $parentkey => $parentrecord) {
                    foreach ($record->parents as $parentid) {
                        if ($parentrecord->idnumber == $parentid) {
                            $parentrecord->childcount++;
                            array_push($foundparents, $parentrecord);
                        }
                    }
                }
                $record->foundparents = $foundparents;
            }
        }
        foreach ($records as $key => $record) {
            $record->related = array();
            if (count($record->foundparents) == 0) {
                $record->parentid = '';
            } else if (count($record->foundparents) == 1) {
                array_push($record->foundparents[0]->children, $record);
                $record->parentid = $record->foundparents[0]->idnumber;
            } else {
                // Multiple parents - choose the one with the least children.
                $chosen = null;
                foreach ($record->foundparents as $parent) {
                    if ($chosen == null || $parent->childcount < $chosen->childcount) {
                        $chosen = $parent;
                    }
                }
                foreach ($record->foundparents as $parent) {
                    if ($chosen !== $parent) {
                        array_push($record->related, $parent);
                    }
                }
                array_push($chosen->children, $record);
                $record->parentid = $chosen->idnumber;
            }
        }

        // Remove from top level any nodes with a parent.
        foreach ($records as $key => $record) {
            if (!empty($record->parentid)) {
                unset($records[$key]);
            }
        }

        $this->tree = $records;
    }

    /**
     * @return array of errors from parsing the xml.
     */
    public function get_error() {
        return $this->error;
    }

    public static function compare_nodes($nodea, $nodeb) {
        $cmp = strcmp($nodea->shortname, $nodeb->shortname);
        if (!$cmp) {
            $cmp = strcmp($nodea->idnumber, $nodeb->idnumber);
        }
        return $cmp;
    }

    private function sort_children($children) {
        usort($children, "\\tool_lpimportreferentiel\\framework_importer::compare_nodes");
        return $children;
    }

    /**
     * Apply some heuristics to get the best possible idnumber, shortname and description.
     *
     * The heuristics are:
     * 1. Clean all params and use shorten_text where needed to stay inside the max length of each field.
     * 2. If the original record had a statementNotation field - this is the most human readable idnumber - use it for both
     *    the idnumber and shortname
     * 3. If the record had a title - use it for the shortname.
     * 4. If the record has children and a short description - use the description for the shortname.
     * 5. Append the subject and educationLevel to the description.
     * 6. If we still don't have a shortname and the idnumber is short (< 16) use it.
     * 7. If we still don't have a shortname and the description is short use it.
     * 8. If we still don't have a shortname just call it "Competency".
     *
     * @param $record The record from the xml file
     * @param $framework - The created framework 
     * @return none - the $record is modified in place
     */
    public function sanitise_competency($record, $framework) {
        $record->shortname = trim(clean_param(shorten_text($record->shortname, 80), PARAM_TEXT));
        if (!empty($record->description)) {
            $record->description = trim(clean_param($record->description, PARAM_TEXT));
        }
        if (!empty($record->betteridnumber)) {
            $record->idnumber = trim(clean_param($record->betteridnumber, PARAM_TEXT));
            $record->shortname = $record->idnumber;
        } else {
            $record->idnumber = trim(clean_param($record->idnumber, PARAM_TEXT));
        }

        $shortdesc = trim(clean_param(shorten_text($record->description, 80), PARAM_TEXT));
        if (!empty($record->children) && $shortdesc == $record->description && empty($record->shortname)) {
            $record->shortname = $shortdesc;
        }
        if (!empty($record->educationLevel)) {
            $record->description .= '<br/>' . get_string('educationlevel', 'tool_lpimportreferentiel', $record->educationLevel);
        }
        if (!empty($record->subject)) {
            $record->description .= '<br/>' . get_string('subject', 'tool_lpimportreferentiel', $record->subject);
        }
        if (empty($record->shortname)) {
            if (!empty($record->idnumber) && (strlen($record->idnumber) < 16)) {
                $record->shortname = $record->idnumber;
            } else if (!empty($shortdesc)) {
                $record->shortname = $shortdesc;
            } else {
                $record->shortname = get_string('competency', 'tool_lpimportreferentiel');
            }
        }

        foreach ($record->children as $child) {
            $this->sanitise_competency($child, $framework);
        }
    }
 
    public function create_competency($parent, $record, $framework) {
        $competency = new stdClass();
        $competency->competencyframeworkid = $framework->get_id();
        $competency->shortname = $record->shortname;
        $competency->description = $record->description;
        $competency->idnumber = $record->idnumber;
        if ($parent) {
            $competency->parentid = $parent->get_id();
        } else {
            $competency->parentid = 0;
        }

        if (!empty($competency->idnumber) && !empty($competency->shortname)) {
            $parent = api::create_competency($competency);

            $record->id = $parent->get_id();

            $record->children = $this->sort_children($record->children);

            foreach ($record->children as $child) {
                $this->create_competency($parent, $child, $framework);
            }

        }
    }

    public function set_related_competencies($record) {
        if (!empty($record->related)) {
            foreach ($record->related as $related) {
                if (!empty($record->id) && !empty($related->id)) {
                    api::add_related_competency($record->id, $related->id);
                }
            }
        }

        foreach ($record->children as $child) {
            $this->set_related_competencies($child);
        }
    }


    /**
     * Recreate the scale config to point to a new scaleid.
     */
    public function get_scale_configuration($scaleid, $config) {
    	$asarray = json_decode($config);
    	$asarray[0]->scaleid = $scaleid;
    	return json_encode($asarray);
    }
    
    /**
     * Search for a global scale that matches this set of scalevalues.
     * If one is not found it will be created.
     */
    public function get_scale_id($scalevalues, $scalename, $scaledescription) {
    	global $CFG, $USER;
    
    	require_once($CFG->libdir . '/gradelib.php');
    
    	$allscales = grade_scale::fetch_all_global();
    	$matchingscale = false;
    	//print_object($allscales);
    	foreach ($allscales as $scale) {
    		if (trim($scale->scale) == trim($scalevalues)) {
    			$matchingscale = $scale;			
    		}
    	}
    	
    	
    	if (!$matchingscale) {
    		// Create it.
    		$newscale = new grade_scale();
    		$newscale->name = get_string('competencyscale', 'tool_lpimportreferentiel', $scalename);
    		$newscale->courseid = 0;
    		$newscale->userid = $USER->id;
    		$newscale->scale = $scalevalues;
    		if (!empty($scaledescription)){
    			$newscale->description = $scaledescription;
    		}
    		else{
    			$newscale->description = get_string('competencyscaledescription', 'tool_lpimportreferentiel');
    		}
    		$newscale->insert();
    		return $newscale->id;
    	}
    	return $matchingscale->id;
    }
    
    /**
     * Search for a global scale that matches this set of scalevalues.
     * If one is not found it will be created.
     */
    public function find_compframework($name) {
    	global $DB;
    	if ($allcfrwks=$DB->get_records('competency_framework',array())){ 		
    		foreach ($allcfrwks as $cfrwk){
    			if (trim($cfrwk->shortname) == trim($name)) {
    				return ($cfrwk);
    			}
    		}
    	}
    	return false;
    }
    
    private function create_framework($scaleid, $scaleconfiguration, $visible) {
        $framework = false;
        if (!empty($this->find_compframework($this->framework->shortname))){
        	return $this->fail(get_string('competencyframeworkexists', 'tool_lpimportreferentiel'));        	
        }
        $record = new stdClass();
        $record->shortname = $this->framework->shortname;
        $record->idnumber = $this->framework->idnumber;
        $record->description = $this->framework->description;
        $record->descriptionformat = FORMAT_HTML;
        
        /** Bareme / Scale imported **/        
        if (isset($this->scalerec) && !empty($this->scalerec->name) && !empty($this->scalerec->scale)){
        	// create a new scale
        	$record->scaleid = $this->get_scale_id($this->scalerec->scale, $this->scalerec->name, $this->scalerec->description) ;
        	// format the competency framework scale        	 
        	$newscaleconfiguration='[{"scaleid":"'.$record->scaleid.'"},';
        	
        	for ($i=1;$i<$this->scale_defaultindex; $i++){
        		$newscaleconfiguration.='{"id":'.$i.',"scaledefault":0,"proficient":0},';
        	}
        	$newscaleconfiguration.='{"id":'.$this->scale_defaultindex.',"scaledefault":1,"proficient":1},';
        	for ($i=$this->scale_defaultindex+1; $i<$this->scale_maxindex; $i++){
        		$newscaleconfiguration.='{"id":'.$i.',"scaledefault":0,"proficient":1},';
        	}
        	$newscaleconfiguration.='{"id":'.$this->scale_maxindex.',"scaledefault":0,"proficient":1}]';        	       				
        	$record->scaleconfiguration = $newscaleconfiguration;
        }
        else {
        	$record->scaleid = $scaleid;
        	$record->scaleconfiguration = $scaleconfiguration;       	
        }
        $record->scaleconfiguration = $this->get_scale_configuration($record->scaleid, $record->scaleconfiguration);          
        
        $record->visible = $visible;
        $record->contextid = context_system::instance()->id;

        $taxdefaults = array();
        $taxcount = 4;
        for ($i = 1; $i <= $taxcount; $i++) {
            $taxdefaults[$i] = \core_competency\competency_framework::TAXONOMY_COMPETENCY;
        }
        $record->taxonomies = $taxdefaults;
		
        try {
            $framework = api::create_framework($record);
        } catch (invalid_persistent_exception $ip) {
            return $this->fail($ip->getMessage());
        }
        
        return $framework;
    }

    /**
     * @param \stdClass containing scaleconfig
     * @return boolean
     */
    public function import($data) {

        $framework = $this->create_framework($data->scaleid, $data->scaleconfiguration, $data->visible);
        if (!$framework) {
            return false;
        }

        foreach ($this->tree as $record) {
            $this->sanitise_competency($record, $framework);
        }
        $this->tree = $this->sort_children($this->tree);
        foreach ($this->tree as $record) {
            $this->create_competency(null, $record, $framework);
        }
        // Not used right now.
        foreach ($this->tree as $record) {
            $this->set_related_competencies($record);
        }
        return $framework;
    }

}
