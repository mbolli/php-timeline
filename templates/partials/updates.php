<?php

/**
 * Everything a live update patches: the timeline and the add-item form's group list, matched by id.
 *
 * @var array $groups TimelineGroup[]
 * @var array $bounds ['min' => string, 'max' => string]
 */
include __DIR__ . '/timeline.php';

include __DIR__ . '/group-select.php';
