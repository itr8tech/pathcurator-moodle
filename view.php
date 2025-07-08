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
 * View a PathCurator instance
 *
 * @package     mod_pathcurator
 * @copyright   2025 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$p = optional_param('p', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('pathcurator', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $pathcurator = $DB->get_record('pathcurator', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $pathcurator = $DB->get_record('pathcurator', array('id' => $p), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $pathcurator->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('pathcurator', $pathcurator->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

$event = \mod_pathcurator\event\course_module_viewed::create(array(
    'objectid' => $pathcurator->id,
    'context' => $modulecontext
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('pathcurator', $pathcurator);
$event->trigger();

// Mark viewed if required.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/pathcurator/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($pathcurator->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

// Add CSS.
$PAGE->requires->css(new moodle_url('/mod/pathcurator/styles.css'));

// Add JavaScript for interactivity.
$PAGE->requires->jquery();
$PAGE->requires->js_amd_inline("
require(['jquery'], function($) {
    $(document).ready(function() {
        // Handle expand all button
        $('#pathcurator-expand-all').on('click', function() {
            $('.pathcurator-step').attr('open', 'open');
        });
        
        // Handle collapse all button
        $('#pathcurator-collapse-all').on('click', function() {
            $('.pathcurator-step').removeAttr('open');
        });
        
        // Handle clear search button
        $('#pathcurator-clear-search').on('click', function() {
            $('#pathcurator-search').val('');
            searchPathway();
        });
        
        // Handle search input
        $('#pathcurator-search').on('input', function() {
            searchPathway();
        });
        
        // Search functionality
        function searchPathway() {
            var searchTerm = $('#pathcurator-search').val().toLowerCase().trim();
            var matchCount = 0;
            var totalSteps = $('.pathcurator-step').length;
            
            if (searchTerm === '') {
                // Show all steps and bookmarks
                $('.pathcurator-step').show();
                $('.card.mb-3').show();
                $('.search-highlight').each(function() {
                    var text = $(this).text();
                    $(this).replaceWith(text);
                });
                $('#pathcurator-search-results').text('');
                return;
            }
            
            // Search through each step
            $('.pathcurator-step').each(function() {
                var stepElement = $(this);
                var stepHasMatch = false;
                var stepName = stepElement.find('summary').text().toLowerCase();
                
                // Check if step name matches
                if (stepName.indexOf(searchTerm) !== -1) {
                    stepHasMatch = true;
                }
                
                // Search through bookmarks in this step
                stepElement.find('.card.mb-3').each(function() {
                    var bookmarkElement = $(this);
                    var bookmarkHasMatch = false;
                    
                    // Get all text content from bookmark
                    var title = bookmarkElement.find('.card-title').text().toLowerCase();
                    var description = bookmarkElement.find('.card-text').text().toLowerCase();
                    var context = bookmarkElement.find('small').text().toLowerCase();
                    var allText = title + ' ' + description + ' ' + context;
                    
                    // Check for matches
                    if (allText.indexOf(searchTerm) !== -1) {
                        bookmarkHasMatch = true;
                        stepHasMatch = true;
                    }
                    
                    // Show/hide bookmark based on match
                    if (bookmarkHasMatch) {
                        bookmarkElement.show();
                        highlightText(bookmarkElement, searchTerm);
                    } else {
                        bookmarkElement.hide();
                    }
                });
                
                // Show/hide step based on match
                if (stepHasMatch) {
                    stepElement.show();
                    stepElement.attr('open', 'open'); // Expand matching steps
                    matchCount++;
                    highlightText(stepElement.find('summary'), searchTerm);
                } else {
                    stepElement.hide();
                }
            });
            
            // Update search results
            var resultsText = '';
            if (matchCount === 0) {
                resultsText = 'No results found for \"' + $('#pathcurator-search').val() + '\"';
            } else {
                resultsText = 'Found ' + matchCount + ' of ' + totalSteps + ' steps matching \"' + $('#pathcurator-search').val() + '\"';
            }
            $('#pathcurator-search-results').text(resultsText);
        }
        
        // Highlight matching text
        function highlightText(element, searchTerm) {
            if (searchTerm === '') return;
            
            element.find('*').addBack().contents().filter(function() {
                return this.nodeType === 3; // Text nodes only
            }).each(function() {
                var text = $(this).text();
                var lowerText = text.toLowerCase();
                var lowerSearchTerm = searchTerm.toLowerCase();
                var index = lowerText.indexOf(lowerSearchTerm);
                
                if (index !== -1) {
                    var beforeText = text.substring(0, index);
                    var matchText = text.substring(index, index + searchTerm.length);
                    var afterText = text.substring(index + searchTerm.length);
                    var highlightedText = beforeText + '<span class=\"search-highlight\">' + matchText + '</span>' + afterText;
                    $(this).replaceWith(highlightedText);
                }
            });
        }
        
        // Cookie helper functions
        function setCookie(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = name + '=' + (value || '') + expires + '; path=/; SameSite=Lax';
        }
        
        function getCookie(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
        
        function getLaunchedLinks() {
            var cookieData = getCookie('pathcurator_launched');
            try {
                return cookieData ? JSON.parse(decodeURIComponent(cookieData)) : [];
            } catch (e) {
                return [];
            }
        }
        
        function saveLaunchedLinks(links) {
            setCookie('pathcurator_launched', encodeURIComponent(JSON.stringify(links)), 365); // 1 year expiry
        }
        
        // Progress tracking functions
        function updateProgress() {
            var totalRequiredLinks = 0;
            var launchedLinks = getLaunchedLinks();
            var requiredPageLinks = [];
            
            $('.pathcurator-launch-btn').each(function() {
                var url = $(this).data('bookmark-url');
                var type = $(this).data('bookmark-type');
                if (url && type === 'required') {
                    requiredPageLinks.push(url);
                    totalRequiredLinks++;
                }
            });
            
            var launchedRequiredCount = 0;
            requiredPageLinks.forEach(function(url) {
                if (launchedLinks.includes(url)) {
                    launchedRequiredCount++;
                }
            });
            
            var percentage = totalRequiredLinks > 0 ? Math.round((launchedRequiredCount / totalRequiredLinks) * 100) : 0;
            
            $('#pathcurator-progress-bar').css('width', percentage + '%').attr('aria-valuenow', percentage);
            $('#pathcurator-progress-text').text(launchedRequiredCount + ' of ' + totalRequiredLinks + ' required links launched (' + percentage + '%)');
        }
        
        // Track launched links in cookies
        $('.pathcurator-launch-btn').on('click', function() {
            var url = $(this).data('bookmark-url');
            if (url) {
                var launchedLinks = getLaunchedLinks();
                if (!launchedLinks.includes(url)) {
                    launchedLinks.push(url);
                    saveLaunchedLinks(launchedLinks);
                }
                
                // Add launched class
                $(this).addClass('btn-success').removeClass('btn-primary');
                
                // Update progress
                updateProgress();
            }
        });
        
        // Check for previously launched links and update progress
        var launchedLinks = getLaunchedLinks();
        $('.pathcurator-launch-btn').each(function() {
            var url = $(this).data('bookmark-url');
            if (url && launchedLinks.includes(url)) {
                $(this).addClass('btn-success').removeClass('btn-primary');
            }
        });
        
        // Initialize progress display
        updateProgress();
    });
});
");

echo $OUTPUT->header();

// Start content container to wrap all activity content
echo html_writer::start_div('container-fluid');
echo html_writer::start_div('row justify-content-center');
echo html_writer::start_div('col-xl-8 col-lg-10');

// Display the activity intro.
if ($pathcurator->intro) {
    echo $OUTPUT->box(format_module_intro('pathcurator', $pathcurator, $cm->id), 'generalbox mod_introbox', 'pathcuratorintro');
}

// Parse and display the pathway data.
if (!empty($pathcurator->jsondata)) {
    $pathwaydata = json_decode($pathcurator->jsondata, true);
    
    if (json_last_error() === JSON_ERROR_NONE && !empty($pathwaydata)) {
        $pathway = reset($pathwaydata); // Get first pathway object
        
        // Display content warning if present.
        if (!empty($pathway['contentWarning'])) {
            echo html_writer::tag('details',
                html_writer::tag('summary', get_string('beforeyoubegin', 'pathcurator'), array('class' => 'alert alert-warning mb-0')) .
                html_writer::div(
                    format_text($pathway['contentWarning'], FORMAT_MARKDOWN),
                    'alert alert-warning mt-0 mb-4'
                ),
                array('class' => 'pathcurator-content-warning mb-3')
            );
        }
        
        // Display pathway description if present.
        if (!empty($pathway['description'])) {
            echo html_writer::div(
                html_writer::tag('h4', get_string('description', 'pathcurator'), array('class' => 'mb-2')) .
                html_writer::tag('p', format_text($pathway['description']), array('class' => 'text-muted')),
                'card card-body bg-light mb-4'
            );
        }
        
        // Progress bar
        echo html_writer::start_div('mb-4');
        echo html_writer::tag('h5', get_string('progresstitle', 'pathcurator'), array('class' => 'mb-2'));
        echo html_writer::start_div('row align-items-center');
        echo html_writer::start_div('col-md-8');
        echo html_writer::start_div('progress', array('style' => 'height: 30px;'));
        echo html_writer::div('', 'progress-bar progress-bar-striped bg-success', array(
            'id' => 'pathcurator-progress-bar',
            'role' => 'progressbar',
            'style' => 'width: 0%',
            'aria-valuenow' => '0',
            'aria-valuemin' => '0',
            'aria-valuemax' => '100'
        ));
        echo html_writer::end_div(); // progress
        echo html_writer::end_div(); // col-md-8
        echo html_writer::start_div('col-md-4 text-right');
        echo html_writer::tag('span', '', array(
            'id' => 'pathcurator-progress-text',
            'class' => 'font-weight-bold'
        ));
        echo html_writer::end_div(); // col-md-4
        echo html_writer::end_div(); // row
        echo html_writer::end_div(); // mb-4
        
        // Add search interface.
        echo html_writer::start_div('mb-4');
        echo html_writer::start_div('row');
        echo html_writer::start_div('col-md-6');
        echo html_writer::tag('label', get_string('searchpathway', 'pathcurator'), 
            array('for' => 'pathcurator-search', 'class' => 'sr-only'));
        echo html_writer::tag('input', '', array(
            'type' => 'text',
            'id' => 'pathcurator-search',
            'class' => 'form-control',
            'placeholder' => get_string('searchplaceholder', 'pathcurator')
        ));
        echo html_writer::end_div();
        echo html_writer::start_div('col-md-6');
        echo html_writer::tag('div', '', array('id' => 'pathcurator-search-results', 'class' => 'text-muted mt-2'));
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Add expand/collapse all buttons.
        echo html_writer::start_div('mb-3');
        echo html_writer::tag('button', get_string('expandall', 'pathcurator'), 
            array('class' => 'btn btn-outline-primary btn-sm mr-2', 'id' => 'pathcurator-expand-all'));
        echo html_writer::tag('button', get_string('collapseall', 'pathcurator'), 
            array('class' => 'btn btn-outline-secondary btn-sm', 'id' => 'pathcurator-collapse-all'));
        echo html_writer::tag('button', get_string('clearsearch', 'pathcurator'), 
            array('class' => 'btn btn-outline-warning btn-sm ml-2', 'id' => 'pathcurator-clear-search'));
        echo html_writer::end_div();
        
        // Display steps.
        if (!empty($pathway['steps'])) {
            echo html_writer::start_div('pathcurator-steps');
            
            $stepnum = 1;
            foreach ($pathway['steps'] as $step) {
                $stephtml = '';
                
                // Step header with objective.
                $stepheader = get_string('step', 'pathcurator', $stepnum) . ': ' . $step['name'];
                if (!empty($step['objective'])) {
                    $stepheader .= html_writer::tag('br', '') . 
                        html_writer::tag('span', 
                            html_writer::tag('em', format_text($step['objective'], FORMAT_MARKDOWN)),
                            array('class' => 'h6 text-dark')
                        );
                }
                
                // Step content.
                $stepcontent = '';
                
                // Add bookmarks.
                if (!empty($step['bookmarks'])) {
                    // Separate required and bonus bookmarks
                    $requiredBookmarks = array();
                    $bonusBookmarks = array();
                    
                    foreach ($step['bookmarks'] as $bookmark) {
                        // Skip bookmarks missing essential fields
                        if (empty($bookmark['title']) || empty($bookmark['url'])) {
                            continue;
                        }
                        
                        $bookmarkType = !empty($bookmark['type']) ? strtolower($bookmark['type']) : 'required';
                        if ($bookmarkType === 'required') {
                            $requiredBookmarks[] = $bookmark;
                        } else {
                            $bonusBookmarks[] = $bookmark;
                        }
                    }
                    
                    $bookmarkshtml = '';
                    
                    // Function to render a bookmark
                    $renderBookmark = function($bookmark) {
                        $bookmarkType = !empty($bookmark['type']) ? strtolower($bookmark['type']) : 'required';
                        $borderclass = $bookmarkType === 'required' ? 'border-primary' : 'border-secondary';
                        
                        $bookmarkhtml = html_writer::start_div('card mb-3 ' . $borderclass);
                        $bookmarkhtml .= html_writer::start_div('card-body');
                        
                        // Badges at the top.
                        $badges = html_writer::start_div('mb-2');
                        
                        // Type badge (Required/Bonus).
                        $typebadgeclass = 'badge badge-' . ($bookmarkType === 'required' ? 'primary' : 'secondary');
                        $typeLabel = $bookmarkType === 'required' ? get_string('required', 'pathcurator') : get_string('bonus', 'pathcurator');
                        $badges .= html_writer::tag('span', $typeLabel, 
                            array('class' => $typebadgeclass));
                        
                        // Content type badge if present.
                        if (!empty($bookmark['contentType'])) {
                            $badges .= ' ' . html_writer::tag('span', $bookmark['contentType'], 
                                array('class' => 'badge badge-info ml-1'));
                        }
                        
                        $badges .= html_writer::end_div();
                        $bookmarkhtml .= $badges;
                        
                        // Title below badges.
                        $bookmarkhtml .= html_writer::tag('h5', $bookmark['title'], 
                            array('class' => 'card-title mb-2'));
                        
                        // Description.
                        if (!empty($bookmark['description'])) {
                            $bookmarkhtml .= html_writer::tag('p', $bookmark['description'], 
                                array('class' => 'card-text'));
                        }
                        
                        // Context.
                        if (!empty($bookmark['context'])) {
                            $bookmarkhtml .= html_writer::div(
                                html_writer::tag('small', 
                                    html_writer::tag('strong', get_string('context', 'pathcurator') . ': ') . 
                                    $bookmark['context']
                                ),
                                'alert alert-light py-2 px-3 mb-3'
                            );
                        }
                        
                        // Launch button - always functional.
                        if (!empty($bookmark['url'])) {
                            $bookmarkhtml .= html_writer::tag('a', get_string('launchlink', 'pathcurator'), 
                                array(
                                    'href' => $bookmark['url'],
                                    'target' => '_blank',
                                    'class' => 'btn btn-primary pathcurator-launch-btn',
                                    'data-bookmark-url' => $bookmark['url'],
                                    'data-bookmark-type' => $bookmarkType
                                )
                            );
                        }
                        
                        $bookmarkhtml .= html_writer::end_div(); // card-body
                        $bookmarkhtml .= html_writer::end_div(); // card
                        return $bookmarkhtml;
                    };
                    
                    // Render required bookmarks first
                    foreach ($requiredBookmarks as $bookmark) {
                        $bookmarkshtml .= $renderBookmark($bookmark);
                    }
                    
                    // Add bonus section header and render bonus bookmarks
                    if (!empty($bonusBookmarks)) {
                        $bookmarkshtml .= html_writer::tag('h4', get_string('bonuslinks', 'pathcurator'), 
                            array('class' => 'mt-4 mb-2'));
                        $bookmarkshtml .= html_writer::tag('p', get_string('bonuslinksdesc', 'pathcurator'), 
                            array('class' => 'text-muted mb-3'));
                        
                        foreach ($bonusBookmarks as $bookmark) {
                            $bookmarkshtml .= $renderBookmark($bookmark);
                        }
                    }
                    
                    if (!empty($bookmarkshtml)) {
                        $stepcontent .= html_writer::div($bookmarkshtml, 'pathcurator-bookmarks');
                    } else {
                        $stepcontent .= html_writer::div(
                            html_writer::tag('p', get_string('nobookmarks', 'pathcurator'), array('class' => 'text-muted')),
                            'alert alert-info'
                        );
                    }
                } else {
                    $stepcontent .= html_writer::div(
                        html_writer::tag('p', get_string('nobookmarks', 'pathcurator'), array('class' => 'text-muted')),
                        'alert alert-info'
                    );
                }
                
                // Add acknowledgments if present (check multiple possible field names).
                $acknowledgmentText = '';
                if (!empty($step['acknowledgments'])) {
                    $acknowledgmentText = $step['acknowledgments'];
                } elseif (!empty($step['acknowledgements'])) {
                    $acknowledgmentText = $step['acknowledgements'];
                } elseif (!empty($step['acknowledgment'])) {
                    $acknowledgmentText = $step['acknowledgment'];
                } elseif (!empty($step['acknowledge'])) {
                    $acknowledgmentText = $step['acknowledge'];
                }
                
                if (!empty($acknowledgmentText)) {
                    $stepcontent .= html_writer::div(
                        html_writer::tag('h5', get_string('acknowledgments', 'pathcurator'), array('class' => 'mb-2')) .
                        html_writer::div(format_text($acknowledgmentText, FORMAT_MARKDOWN)),
                        'card card-body bg-light mb-3'
                    );
                }
                
                // Add pause and reflect if present.
                if (!empty($step['pauseAndReflect'])) {
                    $stepcontent .= html_writer::div(
                        html_writer::tag('h5', get_string('pausereflect', 'pathcurator'), array('class' => 'mb-2')) .
                        html_writer::div(format_text($step['pauseAndReflect'], FORMAT_MARKDOWN)),
                        'card card-body bg-warning mb-3'
                    );
                }
                
                // Create collapsible step using details/summary.
                $stephtml = html_writer::tag('details',
                    html_writer::tag('summary', $stepheader, array('class' => 'pathcurator-step-header')) .
                    html_writer::div($stepcontent, 'pathcurator-step-content'),
                    array('class' => 'pathcurator-step')
                );
                
                echo $stephtml;
                $stepnum++;
            }
            
            echo html_writer::end_div();
        } else {
            echo $OUTPUT->notification(get_string('nosteps', 'pathcurator'), 'info');
        }
        
    } else {
        echo $OUTPUT->notification(get_string('invalidjson', 'pathcurator'), 'error');
    }
} else {
    echo $OUTPUT->notification(get_string('nopathwaydata', 'pathcurator'), 'info');
}

// End content container
echo html_writer::end_div(); // col
echo html_writer::end_div(); // row
echo html_writer::end_div(); // container-fluid

echo $OUTPUT->footer();