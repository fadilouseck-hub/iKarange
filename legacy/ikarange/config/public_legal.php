<?php

/**
 * Public legal pages of the white-label mobile apps, per tenant:
 *   /{tenant}/confidentialite      privacy policy (French, ?lang=en for English)
 *   /{tenant}/suppression-compte   account-deletion request (web form, required by Google Play)
 *
 * The facts about the apps (data collected, processors, security) are written in the page templates from the
 * implementation. Everything the operator must decide or confirm is here. While `published` is false, or while a
 * required field is empty, the pages show a "draft" banner and ask search engines not to index them.
 *
 * TODO(owner): fill the empty fields after legal review, then set 'published' => true.
 */
return [
    'assurplus' => [
        'published' => false,
        'appName' => 'Assur Plus',
        'brandColor' => '#00C896',
        'brandDark' => '#003D2E',
        // Legal entity responsible for the processing (data controller) and its postal address.
        'controller' => '',
        'address' => '',
        // Contact for privacy requests (access, correction, deletion) and general support.
        'privacyEmail' => '',
        'supportEmail' => '',
        'supportPhone' => '',
        // Reference of the declaration / authorisation from the Commission de Protection des Données Personnelles (CDP).
        'cdpReference' => '',
        // How long insurance records must be kept after the contract ends (legal / CIMA obligations), e.g. "10 ans".
        'retentionPeriod' => '',
        // Time to process a deletion request, e.g. "30 jours".
        'deletionDelay' => '30 jours',
        'deletionDelayEn' => '30 days',
        'lastUpdated' => '2026-10-17',
    ],
];
