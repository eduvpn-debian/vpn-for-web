#!/usr/bin/env php
<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use fkooman\OAuth\Client\Http\CurlHttpClient;
use SURFnet\VPN\Web\Config;
use SURFnet\VPN\Web\LogoFetcher;
use SURFnet\VPN\Web\ProviderListFetcher;
use SURFnet\VPN\Web\TwigTpl;

try {
    $config = new Config(require sprintf('%s/config/config.php', dirname(__DIR__)));

    $discoveryUrlList = $config->get('Discovery')->keys();
    foreach ($discoveryUrlList as $discoveryUrl) {
        $publicKey = $config->get('Discovery')->get($discoveryUrl)->get('publicKey');
        $encodedDiscoveryUrl = preg_replace('/[^A-Za-z.]/', '_', $discoveryUrl); // XXX code duplication
        $providerListFetcher = new ProviderListFetcher(sprintf('%s/data/%s', dirname(__DIR__), $encodedDiscoveryUrl));
        $discoveryData = $providerListFetcher->update(new CurlHttpClient(), $discoveryUrl, $publicKey);

        $logoDir = sprintf('%s/data/logo', dirname(__DIR__));
        $logoFetcher = new LogoFetcher($logoDir, new CurlHttpClient());
        $hostNameList = [];
        foreach ($discoveryData['instances'] as $instance) {
            if (false === $hostName = parse_url($instance['base_uri'], PHP_URL_HOST)) {
                throw new RuntimeException('unable to extract hostname');
            }
            $logoFetcher->get($hostName, $instance['logo_uri']);
            $hostNameList[] = ['hostName' => $hostName, 'encodedHostName' => preg_replace('/\./', '\.', $hostName)];
        }

        // generate CSS
        // Templates
        $templateDirs = [
            sprintf('%s/views', dirname(__DIR__)),
            sprintf('%s/config/views', dirname(__DIR__)),
        ];

        $tpl = new TwigTpl($templateDirs, null);
        $logoCssFile = sprintf('%s/%s.css', $logoDir, $encodedDiscoveryUrl);    // XXX strip json from encodedDiscoveryUrl
        if (false === @file_put_contents($logoCssFile, $tpl->render('logo-css', ['hostNameList' => $hostNameList]))) {
            throw new RuntimeException(sprintf('unable to write "%s"', $logoCssFile));
        }
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
