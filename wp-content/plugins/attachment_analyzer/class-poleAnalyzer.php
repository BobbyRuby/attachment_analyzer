<?php

/**
 * Class pole_analyzer
 * Responsible for analyzing and holding data about the current pole being analyzed
 */
class pole_analyzer
{
    public $pole; // holds attachment data in associative array
    public $POST_ARR; // holds post data
    public $pole_handle; // holds pole handle from associated CAD file
    public $mr_req; // holds a true or FALSE value
    public $lowest_power; // holds lowest power
    public $lowest_power_height; // holds lowest power attachment height in inches
    public $lowest_circuit; // holds lowest circuit
    public $lowest_circuit_height; // holds circuit attachment height in inches
    public $lowest_stlt_btm; // holds lowest streetlight attachment
    public $lowest_stlt_btm_height; // holds lowest streetlight attachment height in inches
    public $lowest_trns_btm; // holds lowest transformer attachment
    public $lowest_trns_btm_height; // holds lowest transformer attachment height in inches
    public $highest_comm; // holds highest comm type
    public $highest_comm_height; // holds highest comm attachment height in inches
    public $all_comms; // holds all comms
    public $all_pwrs; // holds all pwrs
    public $all_circuits; // holds all circuits
    public $all_stlt_btms; // all street light bottoms found
    public $all_trans_btms; // all transformer bottoms found
    public $tel_pole_height; // holds tel pole height when pole is TYPE = TELCOPL
    public $tel_pole_atts; // holds tel pole specific varibles (only pole hieght as of 3/17/15)
    public $proposed_attachment_height; // PHOA
    public $pLowDiff; // difference between lowest power and highest comm
    public $slDiff; // difference between lowest circuit and highest comm
    public $stltDiff; // difference between lowest stlt bottom and highest comm
    public $trnsDiff; // difference between lowest trns bottom and highest comm
    public $mr_reasons; // reasons for make ready
    const pwr_abrs = 'p,s,n';
    const primary_abr = 'p';
    const stlt_abrs = 'strl,stl,stlt';
    const traffic_circuit_abrs = 'trfccrt,tcir,traffic,trcir,trccir,trf';
    const plus_sign = '+';
    const btm_abrs = 'btm,bottom';
    const dl_abrs = 'dl,drip';
    const transformer_abrs = 'transformer,trans,trns,tdl, trans dl';
    const pltg_abrs = 'pltg,tg,pl,tag';
    const pl_ht_abrs = 'plht,pl ht,pole height,pole ht';

    function __construct($pole, $POST_ARR)
    {
        $this->pole = $pole;
        $this->POST_ARR = $POST_ARR;
        // get pole handle
        $handle = str_replace("'", "", $pole['HANDLE']);
        $this->pole_handle = $handle;
        // finds all comms
        $this->all_comms = $this->findCommAttachments();
        // finds highest comm
        // and sets $highest_comm & $highest_comm_height
        $this->setHighestComm();

        if ($this->pole['TYPE'] === 'TELCOPL') {
            $this->setTelPoleSpecificVaribles();
        } else {
            // finds lowest power attachment
            // and sets $lowest_power & $lowest_power_height & $all_pwrs
            $this->setPwrAttachmentVariables();
        }
        // finds lowest circuit attachment
        // and sets $lowest_circuit & $lowest_circuit_height & $all_pwrs
        $this->setCircuitAndSteetLightAttachmentVariables();
        // check for make ready issues
        $this->doesNeedMakeReady();
        if ($this->mr_req) {
            $nl = "\n";
            // Push mr into pole
            $this->pole['MR'] = 'Needs Make Ready for the following reason(s):' . $nl;
            foreach ($this->mr_reasons as $reason) {
                $this->pole['MR'] .= $reason . $nl;
            }
        } else {
            // Push mr into pole
            $this->pole['MR'] = '';
        }
        // add proposed hieght of attachment to pole
        $this->pole['PHOA'] = $this->proposed_attachment_height;
    }

    /**
     * Analyzes class variables $lowest_power_height, $lowest_circuit_height, $lowest_stlt_btm_height, and $highest_comm_height to see if there if
     * there is a need for make ready
     * returns true if this pole needs make ready for us to attach or FALSE if not.
     */
    public function doesNeedMakeReady()
    {

        // if highest_comm_height is greater than 0
        if ($this->highest_comm_height > 0) {
            $this->proposed_attachment_height = $this->highest_comm_height + 12;
        } else {
            // no highest comm so make attachments at 22' 2" (266")
            $this->proposed_attachment_height = 266;
        }

        // check to see if we have any circuit / streetlight attachments to check against
        if (count($this->all_circuits)) {
            $this->slDiff = $this->lowest_circuit_height - $this->proposed_attachment_height;
        } else {
            $this->slDiff = 13; // doesn't exist so force to not be make ready
        }

        // check to see if we have any streetlight btm attachments to check against
        if (count($this->all_stlt_btms)) {
            $this->stltDiff = $this->lowest_stlt_btm_height - $this->proposed_attachment_height;
        } else {
            $this->stltDiff = 5; // doesn't exist so force to not be make ready
        }

        // need make ready due to a bottom of stlt
        if ($this->POST_ARR['attalzr_stltDiff']) {
            $stltbtmLcmallowed = $this->POST_ARR['attalzr_stltDiff'];
        } else {
            $stltbtmLcmallowed = 4;
        }
        if ($this->stltDiff < $stltbtmLcmallowed && $this->stltDiff >= 0) {
            $this->mr_req = true;
            $this->mr_reasons[] = 'PHOA within ' . $this->stltDiff . ' inches from bottom of a street light.';
        } elseif ($this->stltDiff < 4 && $this->stltDiff < 0) {
            $this->mr_req = true;
            $this->mr_reasons[] = 'PHOA is ' . abs($this->stltDiff) . ' inches above bottom of a street light.';
        }
        // need make ready due to a circuit?
        if ($this->POST_ARR['attalzr_slDiff']) {
            $cirLcmallowed = $this->POST_ARR['attalzr_slDiff'];
        } else {
            $cirLcmallowed = 12;
        }
        if ($this->slDiff < $cirLcmallowed && $this->slDiff >= 0) {
            $this->mr_req = true;
            $this->mr_reasons[] = 'PHOA within ' . $this->slDiff . ' inches from street light or traffic circuit.';
        } elseif ($this->slDiff < $cirLcmallowed && $this->slDiff < 0) {
            $this->mr_req = true;
            $this->mr_reasons[] = 'PHOA is ' . abs($this->slDiff) . ' inches above a street light or traffic circuit.';
        }

        // is this a telephone pole? check the pole height vs the PHOA
        if ($this->pole['TYPE'] === 'TELCOPL') {
            $this->pLowDiff = $this->tel_pole_height - $this->proposed_attachment_height;

            if ($this->pLowDiff < 4 && $this->pLowDiff >= 0) {
                $this->mr_req = true;
                $this->mr_reasons[] = 'PHOA within ' . $this->pLowDiff . ' inches from top of this pole.';
            } elseif ($this->pLowDiff < 0) {
                $this->mr_req = true;
                $this->mr_reasons[] = 'PHOA is ' . abs($this->pLowDiff) . ' inches above the top of this pole.';
            }
        } else {
            // pwr / joint pole - check against lowest power
            if ($this->lowest_power_height) {
                $this->pLowDiff = $this->lowest_power_height - $this->proposed_attachment_height;
                if ($this->POST_ARR['attalzr_pLowDiff']) {
                    $pLcmallowed = $this->POST_ARR['attalzr_pLowDiff'];
                } else {
                    $pLcmallowed = 40;
                }

                if ($this->pLowDiff < $pLcmallowed && $this->pLowDiff >= 0) {
                    $this->mr_req = true;
                    $this->mr_reasons[] = 'PHOA within ' . $this->pLowDiff . ' inches from lowest power.';
                } elseif ($this->pLowDiff < 0) {
                    $this->mr_req = true;
                    $this->mr_reasons[] = 'PHOA is ' . abs($this->pLowDiff) . ' inches above lowest power.';
                }
            }
        }

        // check the transformer bottom height vs the PHOA
        if ($this->lowest_trns_btm_height) {
            $this->trnsDiff = $this->lowest_trns_btm_height - $this->proposed_attachment_height;
            if ($this->POST_ARR['attalzr_trnsDiff']) {
                $trnsLcmallowed = $this->POST_ARR['attalzr_trnsDiff'];
            } else {
                $trnsLcmallowed = 30;
            }
            if ($this->trnsDiff < $trnsLcmallowed && $this->trnsDiff >= 0) {
                $this->mr_req = true;
                $this->mr_reasons[] = 'PHOA within ' . $this->trnsDiff . ' inches from bottom of lowest transformer.';
            } elseif ($this->trnsDiff < 0) {
                $this->mr_req = true;
                $this->mr_reasons[] = 'PHOA is ' . abs($this->trnsDiff) . ' inches above bottom of lowest transformer.';
            }
        }
    }

    /**
     * Validates data from csv to find the height of an attachment in inches.
     * @param $raw_value - Is the raw value from csv
     */
    private function validateHeightInputs($raw_value)
    {
        $attachment = array(); // if one attachment
        $attachments = array(); // if multiple attachments sent as string and separated by '/'
        $raw_value = trim($raw_value);
        // separate $raw_value into numbers and letters
        // is all numbers?
        $all_numbers = ctype_digit(str_replace(' ', '', $raw_value));
        // if we have any other characters than numbers found
        if (!$all_numbers) {
            // check to see if this data needs split into multiple attachments
            if (stripos($raw_value, '/')) {
                $raw_values = explode('/', $raw_value);
                foreach ($raw_values as $a_raw_value) {
                    $attachment['name'] = strtoupper(trim(preg_replace('#[0-9\'\/"]+#', '', $a_raw_value)));
                    // get numbers and remove whitespace at ends
                    $numbers = preg_replace('/[^ 0-9]+/', '', trim($a_raw_value));
                    $numbers = str_split(trim($numbers), 2); // create array max length 2
                    // if 3 indexes then eliminate space from second and merge second and third
                    if (count($numbers) === 3) {
                        $numbers[1] = str_replace(' ', '', $numbers[1]) . $numbers[2];
                    }
                    // get height in inches
                    error_reporting(0);
                    $attachment['height'] = $this->convertHeightToInches($numbers[0], $numbers[1]);
                    error_reporting(-1);
                    $attachments[] = $attachment;
                }
            } else {
                // one attachment - no '/' present
                $attachment['name'] = strtoupper(trim(preg_replace('/[0-9\'\"]+/', '', $raw_value)));
                // get numbers and remove whitespace at ends
                $numbers = preg_replace('/[^ 0-9]+/', '', trim($raw_value));
                $numbers = str_split(trim($numbers), 2); // create array max length 2 characters
                // if 3 indexes then eliminate space from second and merge second and third
                if (count($numbers) === 3) {
                    $numbers[1] = str_replace(' ', '', $numbers[1]) . $numbers[2];
                }
                // get height in inches
                error_reporting(0);
                $attachment['height'] = $this->convertHeightToInches($numbers[0], $numbers[1]);
                error_reporting(-1);
            }
        } else { // only numbers found - single height value - name specified by column in calling function
            $attachment['name'] = '';
            // get numbers and remove whitespace at ends
            $numbers = trim($raw_value);
            $numbers = str_split($numbers, 2); // create array max length 2
            // get height in inches
            error_reporting(0);
            $attachment['height'] = $this->convertHeightToInches($numbers[0], $numbers[1]);
            error_reporting(-1);
        }
        if (!count($attachments)) {
            // return an associative array with 'name' holding the name of attachment and 'height' holding the height in inches
            return $attachment;
        } else {
            // return an indexed array containing associative arrays with 'name' holding the name of attachment and 'height' holding the height in inches
            return $attachments;
        }

    }

    /**
     * Finds lowest circuit attachments - i.e. - STLT / TRFCCRTs
     * SETS $all_circuits / $lowest_circuit / $lowest_circuit_height
     * SETS $all_stlt_btms / $lowest_stlt_btm / $lowest_stlt_btm_height
     * Should be noted that street light drip loops will be set as circuit attachments while the bottom
     */
    public function setCircuitAndSteetLightAttachmentVariables()
    {
        $cir_attachments = array(); // holds circuits before reordered
        $attachments = array(); // holds circuits in NAME (key) => HEIGHT (value) pairs
        $stlt_attachments = array(); // holds stlt before reordered
        $stl_attachments = array(); // holds stlt in NAME (key) => HEIGHT (value) pairs
        $was_blank = FALSE; // set to false as default
        // find all power attachments by calling validate height inputs on each in for loop and build a list of power
        foreach ($this->pole as $col => $data) {
            // if is one of the BLNK || TRFCCRCT || TLT
            if (strpos($col, 'BLNK') !== FALSE || strpos($col, 'TRFCCRCT') !== FALSE || strpos($col, 'TLT') !== FALSE) {
                if ($data !== '') {
                    $attachment_data = $this->validateHeightInputs($data);
                    // check to see if this is a single value or dual value separated by a '/'
                    if (array_key_exists('name', $attachment_data)) {
                        if ($attachment_data['name'] === '') {
                            $attachment_data['name'] = $col;
                            $was_blank = TRUE; // set to check below before adding column name
                        }
                        if ( // is a street light or traffic circuit attachment or has btm / bottom in name
                            $this->doesMatchAnyAbbreviations($attachment_data['name'], self::stlt_abrs)
                            || $this->doesMatchAnyAbbreviations($attachment_data['name'], self::traffic_circuit_abrs)
                            || $this->doesMatchAnyAbbreviations($attachment_data['name'], self::btm_abrs)
                        ) {
                            if ( // is not a pltg or transformer
                                $this->doesNotMatchAllAbbreviations($attachment_data['name'], self::pltg_abrs)
                                && $this->doesNotMatchAllAbbreviations($attachment_data['name'], self::transformer_abrs)
                            ) {
                                if ( // if is a stlt
                                    $this->doesMatchAnyAbbreviations($attachment_data['name'], self::stlt_abrs)
                                    || $this->doesMatchAnyAbbreviations($attachment_data['name'], self::btm_abrs)
                                ) {
                                    if ( // if stlt has a drip loop then add it to circuits
                                    $this->doesMatchAnyAbbreviations($attachment_data['name'], self::dl_abrs)
                                    ) {
                                        // if this is the blank column OR $was_blank === FALSE
                                        if (strpos($col, 'BLNK') === FALSE || $was_blank === FALSE) {
                                            $cir_attachments[] = $attachment_data;
                                        } else {
                                            // add column name to name of attachment
                                            $attachment_data['name'] = $col . ' ' . $attachment_data['name'];
                                            $cir_attachments[] = $attachment_data;
                                        }
                                    } else { // no drip loop so add to streetlight btm list
                                        // if this is the blank column OR $was_blank === FALSE
                                        if (strpos($col, 'BLNK') === FALSE || $was_blank === FALSE) {
                                            // if btm add the stlt col name
                                            if ($this->doesMatchAnyAbbreviations($attachment_data['name'], self::btm_abrs)) {
                                                // add column name to name of attachment
                                                $attachment_data['name'] = $col . ' ' . $attachment_data['name'];
                                                $stlt_attachments[] = $attachment_data;
                                            } else {
                                                $stlt_attachments[] = $attachment_data;
                                            }
                                        } else {
                                            // add column name to name of attachment
                                            $attachment_data['name'] = $col . ' ' . $attachment_data['name'];
                                            $stlt_attachments[] = $attachment_data;
                                        }
                                    }
                                } else { // not a streetlight so add to circuits
                                    // if this is the blank column OR $was_blank === FALSE
                                    if (strpos($col, 'BLNK') === FALSE || $was_blank === FALSE) {
                                        $cir_attachments[] = $attachment_data;
                                    } else {
                                        // add column name to name of attachment
                                        $attachment_data['name'] = $col . ' ' . $attachment_data['name'];
                                        $cir_attachments[] = $attachment_data;
                                    }
                                }
                            }
                        }
                    } else {
                        // this is a dual value cell with attachments separated by a '/'
                        foreach ($attachment_data as $the_attachment) {
                            if ($the_attachment['name'] === '') {
                                $the_attachment['name'] = $col;
                            }
                            if ( // is a street light or traffic circuit attachment or has btm / bottom in name
                                $this->doesMatchAnyAbbreviations($the_attachment['name'], self::stlt_abrs)
                                || $this->doesMatchAnyAbbreviations($the_attachment['name'], self::traffic_circuit_abrs)
                                || $this->doesMatchAnyAbbreviations($the_attachment['name'], self::btm_abrs)
                            ) {
                                if ( // is not a pltg or transformer
                                    $this->doesNotMatchAllAbbreviations($the_attachment['name'], self::pltg_abrs)
                                    && $this->doesNotMatchAllAbbreviations($the_attachment['name'], self::transformer_abrs)
                                ) {
                                    if ( // if is a stlt
                                        $this->doesMatchAnyAbbreviations($the_attachment['name'], self::stlt_abrs)
                                        || $this->doesMatchAnyAbbreviations($the_attachment['name'], self::btm_abrs)
                                        && $this->doesNotMatchAllAbbreviations($the_attachment['name'], self::primary_abr)
                                    ) {
                                        if ( // if stlt has a drip loop then add it to circuits
                                        $this->doesMatchAnyAbbreviations($the_attachment['name'], self::dl_abrs)
                                        ) {
                                            // if this is the blank column OR $was_blank === FALSE
                                            if (strpos($col, 'BLNK') === FALSE || $was_blank === FALSE) {
                                                $cir_attachments[] = $the_attachment;
                                            } else {
                                                // add column name to name of attachment
                                                $the_attachment['name'] = $col . ' ' . $the_attachment['name'];
                                                $cir_attachments[] = $the_attachment;
                                            }
                                        } else { // no drip loop so add to streetlight btm list
                                            // if this is the blank column OR $was_blank === FALSE
                                            if (strpos($col, 'BLNK') === FALSE || $was_blank === FALSE) {
                                                // if btm add the stlt col name
                                                if ($this->doesMatchAnyAbbreviations($the_attachment['name'], self::btm_abrs)) {
                                                    // add column name to name of attachment
                                                    $the_attachment['name'] = $col . ' ' . $the_attachment['name'];
                                                    $stlt_attachments[] = $the_attachment;
                                                } else {
                                                    $stlt_attachments[] = $the_attachment;
                                                }
                                            } else {
                                                // add column name to name of attachment
                                                $the_attachment['name'] = $col . ' ' . $the_attachment['name'];
                                                $stlt_attachments[] = $the_attachment;
                                            }
                                        }
                                    } elseif ($this->doesNotMatchAllAbbreviations($the_attachment['name'], self::primary_abr)) { // not a streetlight so add to circuits
                                        // if this is the blank column OR $was_blank === FALSE
                                        if (strpos($col, 'BLNK') === FALSE || $was_blank === FALSE) {
                                            $cir_attachments[] = $the_attachment;
                                        } else {
                                            // add column name to name of attachment
                                            $the_attachment['name'] = $col . ' ' . $the_attachment['name'];
                                            $cir_attachments[] = $the_attachment;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // circuit attachments
        // convert to NAME (key) => HEIGHT (value) pairs
        $attachments = $this->convertToNameHeightPairs($cir_attachments);
        // use callable wrapper to find lowest cir
        $lowest_height = $this->getLowestHeight($attachments);
        // move through heights and find the one that matches the lowest circuit
        foreach ($attachments as $key => $val) {
            if ($val === $lowest_height) {
                $this->lowest_circuit = $key;
                $this->lowest_circuit_height = $val;
            }
        }
        $this->all_circuits = $attachments;

        // streetlight attachments
        // convert to NAME (key) => HEIGHT (value) pairs
        $stl_attachments = $this->convertToNameHeightPairs($stlt_attachments);

        // use callable wrapper to find streetlight bottom
        $lowest_stlt_height = $this->getLowestHeight($stl_attachments);
        // move through heights and find the one that matches the highest
        foreach ($stl_attachments as $key => $val) {
            if ($val === $lowest_stlt_height) {
                $this->lowest_stlt_btm = $key;
                $this->lowest_stlt_btm_height = $val;
            }
        }
        $this->all_stlt_btms = $stl_attachments;
    }

    /**
     * As of right now only pulls back and searches for the telephone pole height to use in the doesNeedMakeReady function
     * Doesn't allow any of what power or circuit allow
     */
    public function setTelPoleSpecificVaribles()
    {
        $attachments = array();
        $telpolehieght_attachments = array();
        // find all power attachments by calling validate height inputs on each in for loop and build a list of power
        foreach ($this->pole as $col => $data) {
            // if is one of the LWSTPWR || BLNK
            if (strpos($col, 'LWSTPWR') !== FALSE || strpos($col, 'BLNK') !== FALSE) {
                if ($data !== '') {
                    $attachment_data = $this->validateHeightInputs($data);
                    // check to see if this is a single value or dual value separated by a '/'
                    if (array_key_exists('name', $attachment_data)) {
                        if ($attachment_data['name'] === '') {
                            $attachment_data['name'] = $col;
                        }
                        // check for pole height
                        if ($this->doesMatchAnyAbbreviations($attachment_data['name'], self::pl_ht_abrs)
                        ) {
                            $telpolehieght_attachments[] = $attachment_data;
                        }
                    } else {
                        // this is a dual value cell with attachments separated by a '/'
                        foreach ($attachment_data as $the_attachment) {
                            if ($the_attachment['name'] === '') {
                                $the_attachment['name'] = $col;
                            }
                            // check for pole height
                            if ($this->doesMatchAnyAbbreviations($the_attachment['name'], self::pl_ht_abrs)
                            ) {
                                $telpolehieght_attachments[] = $the_attachment;
                            }
                        }
                    }
                }
            }
        }
        // convert to NAME (key) => HEIGHT (value) pairs
        foreach ($telpolehieght_attachments as $attachment) {
            $attachments[$attachment['name']] = $attachment['height'];
//			If have another need for tel pole this area will be changed
            $this->tel_pole_height = $attachment['height'];
        }
        $this->tel_pole_atts = $attachments;
    }

    /**
     * Finds lowest power attachment
     * SETS $all_pwrs / $lowest_power / $lowest_power_height
     */
    public function setPwrAttachmentVariables()
    {
        $attachments = array();
        $pwr_attachments = array();
        $trans_btm_attachments = array();
        $trns_btm_attachments = array();
        // find all power attachments by calling validate height inputs on each in for loop and build a list of power
        foreach ($this->pole as $col => $data) {
            // if is one of the LWSTPWR || BLNK || TRFCCRCT || TLT
            if (strpos($col, 'LWSTPWR') !== FALSE || strpos($col, 'BLNK') !== FALSE || strpos($col, 'TRFCCRCT') !== FALSE || strpos($col, 'TLT') !== FALSE) {
                if ($data !== '') {
                    $attachment_data = $this->validateHeightInputs($data);
                    // check to see if this is a single value or dual value separated by a '/'
                    if (array_key_exists('name', $attachment_data)) {
                        if ($attachment_data['name'] === '') {
                            $attachment_data['name'] = $col;
                        }
                        // check if this is a power attachment of some sort
                        if ($this->doesMatchAnyAbbreviations($attachment_data['name'], self::pwr_abrs)
                            || $this->doesMatchAnyAbbreviations($attachment_data['name'], self::transformer_abrs)
                        ) {
                            if ( // so street light don't match
                                $this->doesNotMatchAllAbbreviations($attachment_data['name'], self::stlt_abrs)
                                // so traffic circuits don't match
                                && $this->doesNotMatchAllAbbreviations($attachment_data['name'], self::traffic_circuit_abrs)
                                // don't match pole tags
                                && $this->doesNotMatchAllAbbreviations($attachment_data['name'], self::pltg_abrs)
                            ) {
                                // attachment btm is found
                                if ($this->doesMatchAnyAbbreviations($attachment_data['name'], self::btm_abrs)
                                ) {
                                    // transformer not found
                                    if ($this->doesNotMatchAllAbbreviations($attachment_data['name'], self::transformer_abrs)
                                    ) {
                                        // is ok to add to pwr attachments
                                        $pwr_attachments[] = $attachment_data;
                                    } else { // is a transformer btm
                                        $trns_btm_attachments[] = $attachment_data;
                                    }
                                } // any other attachment needed already screened for.
                                else {
                                    $pwr_attachments[] = $attachment_data;
                                }
                            }
                        }
                    } else {
                        // this is a dual value cell with attachments separated by a '/'

                        foreach ($attachment_data as $the_attachment) {
                            if ($the_attachment['name'] === '') {
                                $the_attachment['name'] = $col;
                            }
                            // check if this is a power attachment of some sort
                            if ($this->doesMatchAnyAbbreviations($the_attachment['name'], self::pwr_abrs)
                                || $this->doesMatchAnyAbbreviations($the_attachment['name'], self::transformer_abrs)
                            ) {
                                if ( // so street light don't match
                                    $this->doesNotMatchAllAbbreviations($the_attachment['name'], self::stlt_abrs)
                                    // so traffic circuits don't match
                                    && $this->doesNotMatchAllAbbreviations($the_attachment['name'], self::traffic_circuit_abrs)
                                    // don't match pole tags
                                    && $this->doesNotMatchAllAbbreviations($the_attachment['name'], self::pltg_abrs)
                                ) {
                                    // attachment btm is found
                                    if ($this->doesMatchAnyAbbreviations($the_attachment['name'], self::btm_abrs)
                                    ) {
                                        // transformer not found
                                        if ($this->doesNotMatchAllAbbreviations($the_attachment['name'], self::transformer_abrs)
                                        ) {
                                            // is ok to add to pwr attachments
                                            $pwr_attachments[] = $the_attachment;
                                        } else { // is a transformer btm
                                            $trns_btm_attachments[] = $the_attachment;
                                        }
                                    } // any other attachment needed already screened for.
                                    else {
                                        $pwr_attachments[] = $the_attachment;
                                    }
                                }
                            }
                        }

                    }
                }
            }
        }
        // convert to NAME (key) => HEIGHT (value) pairs
        $attachments = $this->convertToNameHeightPairs($pwr_attachments);

        // use callable wrapper to find lowest pwr
        $lowest_height = $this->getLowestHeight($attachments);
        // move through heights and find the one that matches the lowest
        foreach ($attachments as $key => $val) {
            if ($val === $lowest_height) {
                $this->lowest_power = $key;
                $this->lowest_power_height = $val;
            }
        }
        $this->all_pwrs = $attachments;

        // convert to NAME (key) => HEIGHT (value) pairs
        $trans_btm_attachments = $this->convertToNameHeightPairs($trns_btm_attachments);

        // use callable wrapper to find lowest pwr
        $lowest_trns_btm_height = $this->getLowestHeight($trans_btm_attachments);
        // move through heights and find the one that matches the lowest
        foreach ($trans_btm_attachments as $key => $val) {
            if ($val === $lowest_trns_btm_height) {
                $this->lowest_trns_btm = $key;
                $this->lowest_trns_btm_height = $val;
            }
        }
        $this->all_trans_btms = $trans_btm_attachments;
    }

    /**
     * Finds all communication attachments
     */
    public function findCommAttachments()
    {
        $attachments = array();
        // find all comm attachments by calling validate height inputs on each in for loop and build a list of comm attachments
        foreach ($this->pole as $col => $data) {
            // if is one of the UNKCM or CATV or TELCO columns
            if (strpos($col, 'CM') !== FALSE || strpos($col, 'CATV') !== FALSE || strpos($col, 'TELCO') !== FALSE) {
                if ($data !== '') {
                    $attachment_data = $this->validateHeightInputs($data);
                    if (array_key_exists('name', $attachment_data)) {
                        if ($attachment_data['name'] === '') {
                            $attachment_data['name'] = $col;
                        }
                        $attachments[] = $attachment_data;
                    } else {
                        // this is a dual value cell with attachments separated by a '/'
                        foreach ($attachment_data as $the_attachment) {
                            if ($the_attachment['name'] === '') {
                                $the_attachment['name'] = $col;
                            }
                            $attachments[] = $the_attachment;
                        }
                    }
                }
            }
        }
        return $attachments;
    }

    /**
     * Analyzes heights of communication lines to find the highest one
     * SETs $highest_comm / $highest_comm_height
     * RESETs $this->all_comms to key => value pair
     */
    public function setHighestComm()
    {
        $attachments = array();
        // convert to NAME (key) => HEIGHT (value) pairs
        foreach ($this->all_comms as $attachment) {
            $attachments[$attachment['name']] = $attachment['height'];
        }
        $attachments = $this->convertToNameHeightPairs($this->all_comms);
        // use callable wrapper to find highest comm
        $highest_height = $this->getHighestHeight($attachments);
        // move through heights and find the one that matches the highest
        foreach ($attachments as $key => $val) {
            if ($val === $highest_height) {
                $this->highest_comm = $key;
                $this->highest_comm_height = $val;
            }
        }
        $this->all_comms = $attachments;
    }

    /**
     * Takes a height and converts it to inches
     *
     * @param $feet
     * @param $inches
     * returns height in inches
     */
    public function convertHeightToInches($feet, $inches)
    {
        $hieght_in_inches = ($feet * 12) + $inches;
        return $hieght_in_inches;
    }

    /**
     * @param $attachments_heights - array of heights in inches
     * @return mixed
     */
    private function getLowestHeight($attachment_heights)
    {
        error_reporting(0); // turn errors off
        $lowest_height = call_user_func_array(
            function ($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10) {
                // only 1 attachment return it
                if (!$a2) {
                    return $a1;
                }
                // create crazy high numbers if not set
                if (!$a3) {
                    $a3 = 10000;
                }
                if (!$a4) {
                    $a4 = 10000;
                }
                if (!$a5) {
                    $a5 = 10000;
                }
                if (!$a6) {
                    $a6 = 10000;
                }
                if (!$a7) {
                    $a7 = 10000;
                }
                if (!$a8) {
                    $a8 = 10000;
                }
                if (!$a9) {
                    $a9 = 10000;
                }
                if (!$a10) {
                    $a10 = 10000;
                }
                if ($a1 < $a2
                    && $a1 < $a3
                    && $a1 < $a4
                    && $a1 < $a5
                    && $a1 < $a6
                    && $a1 < $a7
                    && $a1 < $a8
                    && $a1 < $a9
                    && $a1 < $a10
                    && $a1 != 0
                ) {
                    return $a1;
                } elseif ($a2 < $a1
                    && $a2 < $a3
                    && $a2 < $a4
                    && $a2 < $a5
                    && $a2 < $a6
                    && $a2 < $a7
                    && $a2 < $a8
                    && $a2 < $a9
                    && $a2 < $a10 && $a2 != 0
                ) {
                    return $a2;
                } elseif ($a3 < $a1
                    && $a3 < $a2
                    && $a3 < $a4
                    && $a3 < $a5
                    && $a3 < $a6
                    && $a3 < $a7
                    && $a3 < $a8
                    && $a3 < $a9
                    && $a3 < $a10 && $a3 != 0
                ) {
                    return $a3;
                } elseif ($a4 < $a1
                    && $a4 < $a2
                    && $a4 < $a3
                    && $a4 < $a5
                    && $a4 < $a6
                    && $a4 < $a7
                    && $a4 < $a8
                    && $a4 < $a9
                    && $a4 < $a10 && $a4 != 0
                ) {
                    return $a4;
                } elseif ($a5 < $a1
                    && $a5 < $a2
                    && $a5 < $a3
                    && $a5 < $a4
                    && $a5 < $a6
                    && $a5 < $a7
                    && $a5 < $a8
                    && $a5 < $a9
                    && $a5 < $a10 && $a5 != 0
                ) {
                    return $a5;
                } elseif ($a6 < $a1
                    && $a6 < $a2
                    && $a6 < $a3
                    && $a6 < $a4
                    && $a6 < $a5
                    && $a6 < $a7
                    && $a6 < $a8
                    && $a6 < $a9
                    && $a6 < $a10 && $a6 != 0
                ) {
                    return $a6;
                } elseif ($a7 < $a1
                    && $a7 < $a2
                    && $a7 < $a3
                    && $a7 < $a4
                    && $a7 < $a5
                    && $a7 < $a6
                    && $a7 < $a8
                    && $a7 < $a9
                    && $a7 < $a10 && $a7 != 0
                ) {
                    return $a7;
                } elseif ($a8 < $a1
                    && $a8 < $a2
                    && $a8 < $a3
                    && $a8 < $a4
                    && $a8 < $a5
                    && $a8 < $a6
                    && $a8 < $a7
                    && $a8 < $a9
                    && $a8 < $a8
                ) {
                    return $a8;
                } elseif ($a9 < $a1
                    && $a9 < $a2
                    && $a9 < $a3
                    && $a9 < $a4
                    && $a9 < $a5
                    && $a9 < $a6
                    && $a9 < $a7
                    && $a9 < $a8
                    && $a9 < $a10 && $a9 != 0
                ) {
                    return $a9;
                } elseif ($a10 < $a1
                    && $a10 < $a2
                    && $a10 < $a3
                    && $a10 < $a4
                    && $a10 < $a5
                    && $a10 < $a6
                    && $a10 < $a7
                    && $a10 < $a8
                    && $a10 < $a9 && $a10 != 0
                ) {
                    return $a10;
                } elseif ($a1 === $a2
                    && $a1 === $a3
                    && $a1 === $a4
                    && $a1 === $a5
                    && $a1 === $a6
                    && $a1 === $a7
                    && $a1 === $a8
                    && $a1 === $a9
                    && $a1 === $a10
                    && $a1 != 0
                ) {
                    return $a1;
                } elseif ($a2 === $a1
                    && $a2 === $a3
                    && $a2 === $a4
                    && $a2 === $a5
                    && $a2 === $a6
                    && $a2 === $a7
                    && $a2 === $a8
                    && $a2 === $a9
                    && $a2 === $a10 && $a2 != 0
                ) {
                    return $a2;
                } elseif ($a3 === $a1
                    && $a3 === $a2
                    && $a3 === $a4
                    && $a3 === $a5
                    && $a3 === $a6
                    && $a3 === $a7
                    && $a3 === $a8
                    && $a3 === $a9
                    && $a3 === $a10 && $a3 != 0
                ) {
                    return $a3;
                } elseif ($a4 === $a1
                    && $a4 === $a2
                    && $a4 === $a3
                    && $a4 === $a5
                    && $a4 === $a6
                    && $a4 === $a7
                    && $a4 === $a8
                    && $a4 === $a9
                    && $a4 === $a10 && $a4 != 0
                ) {
                    return $a4;
                } elseif ($a5 === $a1
                    && $a5 === $a2
                    && $a5 === $a3
                    && $a5 === $a4
                    && $a5 === $a6
                    && $a5 === $a7
                    && $a5 === $a8
                    && $a5 === $a9
                    && $a5 === $a10 && $a5 != 0
                ) {
                    return $a5;
                } elseif ($a6 === $a1
                    && $a6 === $a2
                    && $a6 === $a3
                    && $a6 === $a4
                    && $a6 === $a5
                    && $a6 === $a7
                    && $a6 === $a8
                    && $a6 === $a9
                    && $a6 === $a10 && $a6 != 0
                ) {
                    return $a6;
                } elseif ($a7 === $a1
                    && $a7 === $a2
                    && $a7 === $a3
                    && $a7 === $a4
                    && $a7 === $a5
                    && $a7 === $a6
                    && $a7 === $a8
                    && $a7 === $a9
                    && $a7 === $a10 && $a7 != 0
                ) {
                    return $a7;
                } elseif ($a8 === $a1
                    && $a8 === $a2
                    && $a8 === $a3
                    && $a8 === $a4
                    && $a8 === $a5
                    && $a8 === $a6
                    && $a8 === $a7
                    && $a8 === $a9
                    && $a8 === $a8
                ) {
                    return $a8;
                } elseif ($a9 === $a1
                    && $a9 === $a2
                    && $a9 === $a3
                    && $a9 === $a4
                    && $a9 === $a5
                    && $a9 === $a6
                    && $a9 === $a7
                    && $a9 === $a8
                    && $a9 === $a10 && $a9 != 0
                ) {
                    return $a9;
                } elseif ($a10 === $a1
                    && $a10 === $a2
                    && $a10 === $a3
                    && $a10 === $a4
                    && $a10 === $a5
                    && $a10 === $a6
                    && $a10 === $a7
                    && $a10 === $a8
                    && $a10 === $a9 && $a10 != 0
                ) {
                    return $a10;
                }
            }, $attachment_heights);
        error_reporting(-1); // turn errors on
        return $lowest_height;
    }

    /**
     * @param $attachments_heights - array of heights in inches
     * @return mixed
     */
    private function getHighestHeight($attachment_heghts)
    {
        error_reporting(0); // turn errors off
        $highest_height = call_user_func_array(
            function ($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10) {
                if ($a1 > $a2 && $a1 > $a3 && $a1 > $a4 && $a1 > $a5 && $a1 > $a6 && $a1 > $a7 && $a1 > $a8 && $a1 > $a9 && $a1 > $a10) {
                    return $a1;
                } elseif ($a2 > $a1
                    && $a2 > $a3
                    && $a2 > $a4
                    && $a2 > $a5
                    && $a2 > $a6
                    && $a2 > $a7
                    && $a2 > $a8
                    && $a2 > $a9
                    && $a2 > $a10
                ) {
                    return $a2;
                } elseif ($a3 > $a1
                    && $a3 > $a2
                    && $a3 > $a4
                    && $a3 > $a5
                    && $a3 > $a6
                    && $a3 > $a7
                    && $a3 > $a8
                    && $a3 > $a9
                    && $a3 > $a10
                ) {
                    return $a3;
                } elseif ($a4 > $a1
                    && $a4 > $a2
                    && $a4 > $a3
                    && $a4 > $a5
                    && $a4 > $a6
                    && $a4 > $a7
                    && $a4 > $a8
                    && $a4 > $a9
                    && $a4 > $a10
                ) {
                    return $a4;
                } elseif ($a5 > $a1
                    && $a5 > $a2
                    && $a5 > $a3
                    && $a5 > $a4
                    && $a5 > $a6
                    && $a5 > $a7
                    && $a5 > $a8
                    && $a5 > $a9
                    && $a5 > $a10
                ) {
                    return $a5;
                } elseif ($a6 > $a1
                    && $a6 > $a2
                    && $a6 > $a3
                    && $a6 > $a4
                    && $a6 > $a5
                    && $a6 > $a7
                    && $a6 > $a8
                    && $a6 > $a9
                    && $a6 > $a10
                ) {
                    return $a6;
                } elseif ($a7 > $a1
                    && $a7 > $a2
                    && $a7 > $a3
                    && $a7 > $a4
                    && $a7 > $a5
                    && $a7 > $a6
                    && $a7 > $a8
                    && $a7 > $a9
                    && $a7 > $a10
                ) {
                    return $a7;
                } elseif ($a8 > $a1
                    && $a8 > $a2
                    && $a8 > $a3
                    && $a8 > $a4
                    && $a8 > $a5
                    && $a8 > $a6
                    && $a8 > $a7
                    && $a8 > $a9
                    && $a8 > $a10
                ) {
                    return $a8;
                } elseif ($a9 > $a1
                    && $a9 > $a2
                    && $a9 > $a3
                    && $a9 > $a4
                    && $a9 > $a5
                    && $a9 > $a6
                    && $a9 > $a7
                    && $a9 > $a8
                    && $a9 > $a10
                ) {
                    return $a9;
                } elseif ($a10 > $a1
                    && $a10 > $a2
                    && $a10 > $a3
                    && $a10 > $a4
                    && $a10 > $a5
                    && $a10 > $a6
                    && $a10 > $a7
                    && $a10 > $a8
                    && $a10 > $a9
                ) {
                    return $a10;
                } elseif ($a1 == $a2
                    && $a1 == $a3
                    && $a1 == $a4
                    && $a1 == $a5
                    && $a1 == $a6
                    && $a1 == $a7
                    && $a1 == $a8
                    && $a1 == $a9
                    && $a1 == $a10
                    && $a1 != 0
                ) {
                    return $a1;
                } elseif ($a2 == $a1
                    || $a2 == $a3
                    || $a2 == $a4
                    || $a2 == $a5
                    || $a2 == $a6
                    || $a2 == $a7
                    || $a2 == $a8
                    || $a2 == $a9
                    || $a2 == $a10 || $a2 != 0
                ) {
                    return $a2;
                } elseif ($a3 == $a1
                    || $a3 == $a2
                    || $a3 == $a4
                    || $a3 == $a5
                    || $a3 == $a6
                    || $a3 == $a7
                    || $a3 == $a8
                    || $a3 == $a9
                    || $a3 == $a10 || $a3 != 0
                ) {
                    return $a3;
                } elseif ($a4 == $a1
                    || $a4 == $a2
                    || $a4 == $a3
                    || $a4 == $a5
                    || $a4 == $a6
                    || $a4 == $a7
                    || $a4 == $a8
                    || $a4 == $a9
                    || $a4 == $a10 || $a4 != 0
                ) {
                    return $a4;
                } elseif ($a5 == $a1
                    || $a5 == $a2
                    || $a5 == $a3
                    || $a5 == $a4
                    || $a5 == $a6
                    || $a5 == $a7
                    || $a5 == $a8
                    || $a5 == $a9
                    || $a5 == $a10 || $a5 != 0
                ) {
                    return $a5;
                } elseif ($a6 == $a1
                    || $a6 == $a2
                    || $a6 == $a3
                    || $a6 == $a4
                    || $a6 == $a5
                    || $a6 == $a7
                    || $a6 == $a8
                    || $a6 == $a9
                    || $a6 == $a10 || $a6 != 0
                ) {
                    return $a6;
                } elseif ($a7 == $a1
                    || $a7 == $a2
                    || $a7 == $a3
                    || $a7 == $a4
                    || $a7 == $a5
                    || $a7 == $a6
                    || $a7 == $a8
                    || $a7 == $a9
                    || $a7 == $a10 || $a7 != 0
                ) {
                    return $a7;
                } elseif ($a8 == $a1
                    || $a8 == $a2
                    || $a8 == $a3
                    || $a8 == $a4
                    || $a8 == $a5
                    || $a8 == $a6
                    || $a8 == $a7
                    || $a8 == $a9
                    || $a8 == $a8
                ) {
                    return $a8;
                } elseif ($a9 == $a1
                    || $a9 == $a2
                    || $a9 == $a3
                    || $a9 == $a4
                    || $a9 == $a5
                    || $a9 == $a6
                    || $a9 == $a7
                    || $a9 == $a8
                    || $a9 == $a10 || $a9 != 0
                ) {
                    return $a9;
                } elseif ($a10 == $a1
                    || $a10 == $a2
                    || $a10 == $a3
                    || $a10 == $a4
                    || $a10 == $a5
                    || $a10 == $a6
                    || $a10 == $a7
                    || $a10 == $a8
                    || $a10 == $a9 || $a10 != 0
                ) {
                    return $a10;
                }
            }, $attachment_heghts);
        error_reporting(-1); // turn errors on
        return $highest_height;
    }

    /**
     * @param $attachments
     * @return mixed
     */
    private function convertToNameHeightPairs($attachments)
    {
        foreach ($attachments as $attachment) {
            // if is duplicate then append to name before adding to array
            if (array_key_exists($attachment['name'], $attachments)) {
                $same_name_count = 0;
                $attachments_keys = array_keys($attachments);
                foreach ($attachments_keys as $attachment_key) {
                    // if the name is found in the key add to the count
                    if (stripos($attachment_key, $attachment['name'])) {
                        $same_name_count++;
                    }
                }
                $attachment['name'] = $attachment['name'] . '-' . count($same_name_count);
            }
            $attachments[$attachment['name']] = $attachment['height'];
        }
        // remove indexed values
        foreach ($attachments as $attachment_key => $val) {
            if (is_int($attachment_key)) {
                unset($attachments[$attachment_key]);
            }
        }
        return $attachments;
    }

    /**
     * Meant to replace large conditional || statements and cycles through abbreviations array and searches for them in the attachment name passed.  If ANY are found it returns true.
     * @param $attachment_name
     * @param $abbreviations
     * @return bool
     */
    private function doesMatchAnyAbbreviations($attachment_name, $abbreviations)
    {
        $abbreviations = explode(',', $abbreviations);
        foreach ($abbreviations as $abr) {
            if (stripos($attachment_name, $abr) !== FALSE) return TRUE;
        }
        return FALSE;
    }

    /**
     * Meant to replace large conditional && statements and cycles through abbreviations array and searches for them in the attachment name passed.  If ALL are found it returns true.
     * @param $attachment_name
     * @param $abbreviations
     * @return bool
     */
    private function doesNotMatchAllAbbreviations($attachment_name, $abbreviations)
    {
        $matches = array();
        $abbreviations = explode(',', $abbreviations);
        foreach ($abbreviations as $abr) {
            if (stripos($attachment_name, $abr) === FALSE) $matches[] = FALSE;
        }
        // if all were false then then the
        if (count($abbreviations) === count($matches))
            return TRUE;
    }
}