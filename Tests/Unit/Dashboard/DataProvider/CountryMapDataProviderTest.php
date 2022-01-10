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

namespace Waldhacker\Plausibleio\Tests\Unit\Dashboard\DataProvider;

use GuzzleHttp\Client;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider;
use Waldhacker\Plausibleio\Services\ISO3166Service;
use Waldhacker\Plausibleio\Services\ISO3166_2_Service;
use Waldhacker\Plausibleio\Services\LocationCodeService;
use Waldhacker\Plausibleio\Services\ConfigurationService;
use Waldhacker\Plausibleio\Services\PlausibleService;

class CountryMapDataProviderTest extends UnitTestCase
{
    use ProphecyTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->languageServiceProphecy = $this->prophesize(LanguageService::class);
        $GLOBALS['LANG'] = $this->languageServiceProphecy->reveal();
    }

    public function getCountryDataForDataMapReturnsProperValuesDataProvider(): \Generator
    {
        yield 'all items are transformed' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['country' => 'DE', 'visitors' => 9],
                ['country' => 'US', 'visitors' => 3],
            ],
            'expected' => [
                'data' => [
                    ['alpha2' => 'DE', 'alpha3' => 'DEU', 'country' => 'Germany', 'visitors' => 9, 'percentage' => 75.0],
                    ['alpha2' => 'US', 'alpha3' => 'USA', 'country' => 'United States of America', 'visitors' => 3, 'percentage' => 25.0],
                ],
                'columns' => [
                    [
                        'name' => 'country',
                        'label' => 'Country',
                        'filter' => [
                            'name' => 'visit:country',
                            'value' => 'alpha2',
                            'label' => 'Country is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without country are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['country' => 'DE', 'visitors' => 3],
                ['country' => '', 'visitors' => 9],
                ['visitors' => 4],
            ],
            'expected' => [
                'data' => [
                    ['alpha2' => 'DE', 'alpha3' => 'DEU', 'country' => 'Germany', 'visitors' => 3, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'country',
                        'label' => 'Country',
                        'filter' => [
                            'name' => 'visit:country',
                            'value' => 'alpha2',
                            'label' => 'Country is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without visitors are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['country' => 'DE', 'visitors' => 3],
                ['country' => 'US', 'visitors' => null],
                ['country' => 'US'],
            ],
            'expected' => [
                'data' => [
                    ['alpha2' => 'DE', 'alpha3' => 'DEU', 'country' => 'Germany', 'visitors' => 3, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'country',
                        'label' => 'Country',
                        'filter' => [
                            'name' => 'visit:country',
                            'value' => 'alpha2',
                            'label' => 'Country is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items with invalid iso code are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['country' => 'DE', 'visitors' => 3],
                ['country' => '_', 'visitors' => 4],
            ],
            'expected' => [
                'data' => [
                    ['alpha2' => 'DE', 'alpha3' => 'DEU', 'country' => 'Germany', 'visitors' => 3, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'country',
                        'label' => 'Country',
                        'filter' => [
                            'name' => 'visit:country',
                            'value' => 'alpha2',
                            'label' => 'Country is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'all items are transformed with filter' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [['name' => 'visit:country', 'value' => 'DE']],
            'endpointData' => [
                ['region' => 'DE-BB', 'visitors' => 9,],
            ],
            'expected' => [
                'data' => [
                    ['region' => 'Brandenburg', 'isoCode' => 'DE-BB', 'visitors' => 9, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'region',
                        'label' => 'Region',
                        'filter' => [
                            'name' => 'visit:region',
                            'value' => 'isoCode',
                            'label' => 'Region is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getCountryDataForDataMapReturnsProperValuesDataProvider
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::__construct
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::getCountryDataForDataMap
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::getCountryData
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::plausibleCountriesToDataMap
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::plausibleRegionsToDataMap
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::getLanguageService
     * @covers \Waldhacker\Plausibleio\Services\PlausibleService::__construct
     * @covers \Waldhacker\Plausibleio\Services\PlausibleService::isFilterActivated
     * @covers \Waldhacker\Plausibleio\Services\PlausibleService::filtersToPlausibleFilterString
     * @covers \Waldhacker\Plausibleio\Services\PlausibleService::calcPercentage
     * @covers \Waldhacker\Plausibleio\Services\PlausibleService::dataCleanUp
     * @covers \Waldhacker\Plausibleio\Services\ISO3166Service::alpha2
     * @covers \Waldhacker\Plausibleio\Services\ISO3166Service::search
     * @covers \Waldhacker\Plausibleio\Services\ISO3166_2_Service::region
     */
    public function getCountryDataForDataMapReturnsProperValues(
        string $plausibleSiteId,
        string $timeFrame,
        array $filters,
        ?array $endpointData,
        array $expected
    ): void {
        $configurationServiceProphecy = $this->prophesize(ConfigurationService::class);
        $plausibleServiceMock = $this->getMockBuilder(PlausibleService::class)
            ->onlyMethods(['sendAuthorizedRequest'])
            ->setConstructorArgs([
                new RequestFactory(),
                new Client(),
                $configurationServiceProphecy->reveal(),
            ])
            ->getMock();

        $this->languageServiceProphecy->getLL('barChart.labels.visitors')->willReturn('Visitors');
        $this->languageServiceProphecy->getLL('barChart.labels.country')->willReturn('Country');
        $this->languageServiceProphecy->getLL('barChart.labels.region')->willReturn('Region');
        $this->languageServiceProphecy->getLL('barChart.labels.city')->willReturn('City');
        $this->languageServiceProphecy->getLL('filter.locationData.countryIs')->willReturn('Country is');
        $this->languageServiceProphecy->getLL('filter.locationData.regionIs')->willReturn('Region is');
        $this->languageServiceProphecy->getLL('filter.locationData.cityIs')->willReturn('City is');

        $authorizedRequestParams = [
            'site_id' => $plausibleSiteId,
            'period' => $timeFrame,
            'property' => $filters ? 'visit:region' : 'visit:country',
        ];
        if (!empty($filters)) {
            $authorizedRequestParams['filters'] = $filters[0]['name'] . '==' . $filters[0]['value'];
        }

        $plausibleServiceMock->expects($this->exactly(1))
            ->method('sendAuthorizedRequest')
            ->with(
                $plausibleSiteId,
                'api/v1/stats/breakdown?',
                $authorizedRequestParams,
            )
            ->willReturn($endpointData);

        $subject = new CountryMapDataProvider($plausibleServiceMock, new ISO3166Service(), new ISO3166_2_Service(), new LocationCodeService());
        self::assertSame($expected, $subject->getCountryDataForDataMap($plausibleSiteId, $timeFrame, $filters));
    }

    public function getCountryDataOnlyForDataMapReturnsProperValuesDataProvider(): \Generator
    {
        yield 'all items are transformed' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['country' => 'DE', 'visitors' => 9],
                ['country' => 'US', 'visitors' => 3],
            ],
            'expected' => [
                'data' => [
                    ['alpha2' => 'DE', 'alpha3' => 'DEU', 'country' => 'Germany', 'visitors' => 9, 'percentage' => 75.0],
                    ['alpha2' => 'US', 'alpha3' => 'USA', 'country' => 'United States of America', 'visitors' => 3, 'percentage' => 25.0],
                ],
                'columns' => [
                    [
                        'name' => 'country',
                        'label' => 'Country',
                        'filter' => [
                            'name' => 'visit:country',
                            'value' => 'alpha2',
                            'label' => 'Country is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without country are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['country' => 'DE', 'visitors' => 3],
                ['country' => '', 'visitors' => 9],
                ['visitors' => 4],
            ],
            'expected' => [
                'data' => [
                    ['alpha2' => 'DE', 'alpha3' => 'DEU', 'country' => 'Germany', 'visitors' => 3, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'country',
                        'label' => 'Country',
                        'filter' => [
                            'name' => 'visit:country',
                            'value' => 'alpha2',
                            'label' => 'Country is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without visitors are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['country' => 'DE', 'visitors' => 3],
                ['country' => 'US', 'visitors' => null],
                ['country' => 'US'],
            ],
            'expected' => [
                'data' => [
                    ['alpha2' => 'DE', 'alpha3' => 'DEU', 'country' => 'Germany', 'visitors' => 3, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'country',
                        'label' => 'Country',
                        'filter' => [
                            'name' => 'visit:country',
                            'value' => 'alpha2',
                            'label' => 'Country is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items with invalid iso code are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['country' => 'DE', 'visitors' => 3],
                ['country' => '_', 'visitors' => 4],
            ],
            'expected' => [
                'data' => [
                    ['alpha2' => 'DE', 'alpha3' => 'DEU', 'country' => 'Germany', 'visitors' => 3, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'country',
                        'label' => 'Country',
                        'filter' => [
                            'name' => 'visit:country',
                            'value' => 'alpha2',
                            'label' => 'Country is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'all items are transformed with filter' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [['name' => 'visit:country', 'value' => 'DE']],
            'endpointData' => [
                ['country' => 'DE', 'visitors' => 9],
            ],
            'expected' => [
                'data' => [
                    ['alpha2' => 'DE', 'alpha3' => 'DEU', 'country' => 'Germany', 'visitors' => 9, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'country',
                        'label' => 'Country',
                        'filter' => [
                            'name' => 'visit:country',
                            'value' => 'alpha2',
                            'label' => 'Country is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getCountryDataOnlyForDataMapReturnsProperValuesDataProvider
     * @covers       \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::__construct
     * @covers       \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::getCountryDataOnlyForDataMap
     * @covers       \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::plausibleCountriesToDataMap
     * @covers       \Waldhacker\Plausibleio\Dashboard\DataProvider\CountryMapDataProvider::getLanguageService
     * @covers       \Waldhacker\Plausibleio\Services\PlausibleService::__construct
     * @covers       \Waldhacker\Plausibleio\Services\PlausibleService::isFilterActivated
     * @covers       \Waldhacker\Plausibleio\Services\PlausibleService::filtersToPlausibleFilterString
     * @covers       \Waldhacker\Plausibleio\Services\PlausibleService::removeFilter
     * @covers       \Waldhacker\Plausibleio\Services\PlausibleService::calcPercentage
     * @covers       \Waldhacker\Plausibleio\Services\PlausibleService::dataCleanUp
     * @covers       \Waldhacker\Plausibleio\Services\ISO3166Service::alpha2
     * @covers       \Waldhacker\Plausibleio\Services\ISO3166Service::search
     */
    public function getCountryDataOnlyForDataMapReturnsProperValues(
        string $plausibleSiteId,
        string $timeFrame,
        array $filters,
        ?array $endpointData,
        array $expected
    ): void
    {
        $configurationServiceProphecy = $this->prophesize(ConfigurationService::class);
        $plausibleServiceMock = $this->getMockBuilder(PlausibleService::class)
            ->onlyMethods(['sendAuthorizedRequest'])
            ->setConstructorArgs([
                new RequestFactory(),
                new Client(),
                $configurationServiceProphecy->reveal(),
            ])
            ->getMock();

        $this->languageServiceProphecy->getLL('barChart.labels.visitors')->willReturn('Visitors');
        $this->languageServiceProphecy->getLL('barChart.labels.country')->willReturn('Country');
        $this->languageServiceProphecy->getLL('filter.locationData.countryIs')->willReturn('Country is');

        $authorizedRequestParams = [
            'site_id' => $plausibleSiteId,
            'period' => $timeFrame,
            'property' => 'visit:country',
        ];
        if (!empty($filters)) {
            $authorizedRequestParams['filters'] = $filters[0]['name'] . '==' . $filters[0]['value'];
        }

        $plausibleServiceMock->expects($this->exactly(1))
            ->method('sendAuthorizedRequest')
            ->with(
                $plausibleSiteId,
                'api/v1/stats/breakdown?',
                $authorizedRequestParams,
            )
            ->willReturn($endpointData);

        $subject = new CountryMapDataProvider($plausibleServiceMock, new ISO3166Service(), new ISO3166_2_Service(), new LocationCodeService());
        self::assertSame($expected, $subject->getCountryDataOnlyForDataMap($plausibleSiteId, $timeFrame, $filters));
    }
}
