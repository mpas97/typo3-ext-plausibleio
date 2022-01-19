<?php

declare(strict_types=1);

/*
 * This file is part of the plausibleio extension for TYPO3
 * - (c) 2021 waldhacker UG (haftungsbeschränkt)
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Waldhacker\Plausibleio\Dashboard\DataProvider;

use TYPO3\CMS\Core\Localization\LanguageService;
use Waldhacker\Plausibleio\Services\PlausibleService;

class GoalDataProvider
{
    private const EXT_KEY = 'plausibleio';
    private PlausibleService $plausibleService;

    public function __construct(PlausibleService $plausibleService)
    {
        $this->plausibleService = $plausibleService;
    }

    public function getGoalPropertiesData(string $goal, string $plausibleSiteId, string $timeFrame, array $filters = []): array
    {
        $result = [];

        // Plausible does not currently offer an endpoint to determine the properties of
        // an event.
        // For the 404 event, the property is known (path), at least if the event was generated
        // by the 404Tracking middleware.
        if ($goal == '404') {
            $properties = ['path'];

            foreach ($properties as $prop) {
                $endpoint = 'api/v1/stats/breakdown?';
                $params = [
                    'site_id' => $plausibleSiteId,
                    'period' => $timeFrame,
                    'property' => 'event:props:' . $prop,
                    'metrics' => 'visitors,events',
                ];
                $filterStr = $this->plausibleService->filtersToPlausibleFilterString($filters);
                if ($filterStr) {
                    $params['filters'] = $filterStr;
                }

                $currentIndex = count($result);
                $result[$currentIndex]['data'] = $this->plausibleService->sendAuthorizedRequest($plausibleSiteId, $endpoint, $params);
                if (!is_array($result[$currentIndex]['data'])) {
                    $result[$currentIndex]['data'] = [];
                }

                $result[$currentIndex]['data'] = $this->plausibleService->dataCleanUp([$prop, 'visitors', 'events'], $result[$currentIndex]['data']);
                $result[$currentIndex]['data'] = $this->plausibleService->calcPercentage($result[$currentIndex]['data']);
                $result[$currentIndex]['data'] = $this->plausibleService->calcConversionRate($plausibleSiteId, $timeFrame, $result[$currentIndex]['data']);

                $result[$currentIndex]['columns'] = [
                    [
                        'name' => $prop,
                        'filter' => [
                            'name' => 'event:goal:props',
                            'label' => $this->getLanguageService()->getLL('filter.goalData.goalPropertyIs'),
                        ],
                    ],
                    ['name' => 'visitors'],
                    ['name' => 'events'],
                    ['name' => 'cr'],
                ];
            }
        }

        return $result;
    }

    public function getGoalsData(string $plausibleSiteId, string $timeFrame, array $filters = []): array
    {
        $goalFilterActivated = $this->plausibleService->isFilterActivated('event:goal', $filters);

        $endpoint = 'api/v1/stats/breakdown?';
        $params = [
            'site_id' => $plausibleSiteId,
            'period' => $timeFrame,
            'property' => 'event:goal',
            'metrics' => 'visitors,events',
        ];
        $filterStr = $this->plausibleService->filtersToPlausibleFilterString($filters);
        if ($filterStr) {
            $params['filters'] = $filterStr;
        }

        $responseData = [];
        $responseData['data'] = $this->plausibleService->sendAuthorizedRequest($plausibleSiteId, $endpoint, $params);
        if (!is_array($responseData['data'])) {
            $responseData['data'] = [];
        }

        $map = $this->plausibleService->dataCleanUp(['goal', 'visitors', 'events'], $responseData['data']);
        $map = $this->plausibleService->calcPercentage($map);
        $map = $this->plausibleService->calcConversionRate($plausibleSiteId, $timeFrame, $map);
        $responseData['data'] = $map;
        if ($goalFilterActivated && count($responseData['data']) > 0) {
            $subdata = $this->getGoalPropertiesData(
                $this->plausibleService->getFilterValue('event:goal', $filters),
                $plausibleSiteId,
                $timeFrame,
                $filters
            );
            if (count($subdata) > 0) {
                $responseData['data'][0]['subData'] = $subdata;
            }
        }

        $responseData['columns'] = [
                [
                    'name' => 'goal',
                    'label' => $this->getLanguageService()->getLL('barChart.labels.goal'),
                    'filter' => [
                        'name' => 'event:goal',
                        'label' => $this->getLanguageService()->getLL('filter.goalData.goalIs'),
                    ],
                ],
                [
                    'name' => 'visitors',
                    'label' => $this->getLanguageService()->getLL('barChart.labels.uniques'),
                ],
                [
                    'name' => 'events',
                    'label' => $this->getLanguageService()->getLL('barChart.labels.total'),
                ],
                [
                    'name' => 'cr',
                    'label' => $this->getLanguageService()->getLL('barChart.labels.cr'),
                ],
            ];

        return $responseData;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
