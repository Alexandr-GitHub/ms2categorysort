<?php

declare(strict_types=1);

namespace Ms2CategorySort\Domain;

final class XpdoMapExtension
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function forMsCategoryMember(): array
    {
        return [
            'msCategoryMember' => [
                'fields' => [
                    'menuindex' => 0,
                ],
                'fieldMeta' => [
                    'menuindex' => [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                        'default' => 0,
                    ],
                ],
                'indexes' => [
                    'category_menuindex' => [
                        'alias' => 'category_menuindex',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' => [
                            'category_id' => [
                                'length' => '',
                                'collation' => 'A',
                                'null' => false,
                            ],
                            'menuindex' => [
                                'length' => '',
                                'collation' => 'A',
                                'null' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
