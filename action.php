<?php
 
/**
 * Example Action Plugin: Example Component.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Anton Caesar <caesaranton700@yahoo.de>
 */
 
class action_plugin_sabersearch extends DokuWiki_Action_Plugin {

    protected $content = "";
    protected $prompturl = "";
 
    /**
     * Register its handlers with the DokuWiki's event controller
     */
    public function register(Doku_Event_Handler $controller) {
        // $controller->register_hook('SEARCH_QUERY_FULLPAGE', 'AFTER', 
        //                            $this, 'crossSearch');
        $controller->register_hook('SEARCH_QUERY_PAGELOOKUP', 'AFTER', $this, 'crossSearch');
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'addResults');
    }
 
    /**
     * Prepares and executes the prompt url
     */
    public function crossSearch(Doku_Event $event) {
        if ($this->getConf('enable') == 1) {
            $prompt = $event->data['id'];
            $time = $event->data['after'];
            $this->prompturl = $this->prepareBaseURL($prompt); // Prepares the url from the configuration for the search prompt

            if ($time != "") // Adds optional time parameters, if they exist
                $this->prompturl .= "&min=" . $time;
            $this->prompturl = str_replace(" ", "%20", $this->prompturl); // Replaces speces with '%20' to prepare the prompt url for the browser

            $resultpage = file_get_html($this->prompturl); // Save page html content into a variable
            $doc = new DOMDocument();  // Set up the DOMDocument for the page content
            libxml_use_internal_errors(true); // Silences annoying errors

            if($resultpage != null) {
                $doc->loadHTML($resultpage); // Load HTML as a hierarchical DOMDocument
                libxml_clear_errors(); // Again, deal with errors
                $divs = $doc->getElementsByTagName('div'); // Get all div elements into a list

                foreach($divs as $div) {
                    if($div->getAttribute('class') === 'search_quickresult') { // Renames the class of the external quickresults to something destinct
                        $div->setAttribute('class', 'search_quickresult_embed');
                        $this->fixExternalLinks($div);
                        $this->content .= $doc->saveHTML($div);
                    }
                    
                    if($div->getAttribute('class') === 'search_fulltextresult') { // Renames the class of the external fulltextresults to something destinct
                        $div->setAttribute('class', 'search_fulltextresult_embed');
                        $this->fixExternalLinks($div);
                        $this->content .= $doc->saveHTML($div);
                    }
                }
            }
        }
    }

    /**
     * Adds the external results to the content of the search page
     */
    public function addResults(Doku_Event $event) {
        if ($this->getConf('enable') == 1) {
            if (str_contains($event->data, "do=search") && $this->content != "") { // Only display results section if on search page and the results are not empty
                $pagecontent = $event->data;
                $event->data .= '<div class="saberlink-embed" style="border:1px solid #DDDDDD; border-radius:8px; margin:-8px; padding:8px">';
                $event->data .= '<p style="font-size: 12px; color: #888888"><span style="background-color: #EEEEEE; padding: 3px; border-radius:4px"> Embedded from: <a href="' . $this->prompturl . '">' . $this->prompturl . '</a></span></p>';
                $event->data .= $this->content;
                $event->data .= '</div>';
            }
        }
    }

    /**
     * Builds the search prompt url from the base url specified in the configuration
     */
    public function prepareBaseURL($prompt) {
        $baseurl = $this->getConf('url');

        // Expands the base url if necessary
        if (!str_ends_with($baseurl, "/")) {
            $baseurl .= "/";
        }
        if (!str_starts_with($baseurl, "https://")) {
            $baseurl = "https://" . $baseurl;
        }

        // Prepares the base url based on the rewriting method the external wiki uses
        if ($this->getConf('url-rewrite') == 'webserver (1)') {
            $baseurl .= "start?do=search&sf=1&q=";
        }
        else if ($this->getConf('url-rewrite') == 'dokuwiki (2)') {
            $baseurl .= "doku.php/start?do=search&sf=1&q=";
        }
        else { // no rewriting currently not supported
            // $baseurl .= "doku.php?do=search&id=";
        }

        return $baseurl . $prompt;
    }

    /**
     * Fixes the links from the external results to point to the external wiki again
     */
    public function fixExternalLinks($div) {
        $childs = $div->getElementsByTagName('*'); // Get all child elements of dw-content into a list
        $baseurl = $this->getConf('url');
        if(str_ends_with($baseurl, "/")) {
            $baseurl = substr($baseurl, 0, -1); // If the url ends with a "/", remove it
        }
        $baseurl = parse_url($baseurl, PHP_URL_HOST); // Get the base url from the url string
        foreach($childs as $child) {
            if($child->hasAttribute('href')) {
                if(str_starts_with($child->getAttribute('href'), "/")) {
                    $child->setAttribute('href', 'https://' . $baseurl . $child->getAttribute('href')); // If the child has a href attribute that starts with /, add the base url before it
                }
            }
            if($child->hasAttribute('src')) {
                if(str_starts_with($child->getAttribute('src'), "/")) {
                    $child->setAttribute('src', 'https://' . $baseurl . $child->getAttribute('src')); // If the child has a src attribute that starts with /, add the base url before it
                }
            }
        }
    }
}