<?php

return array(
    'migration_table' => '_migration',  //имя таблицы миграций
    'path' => '/common/migrations/lib/live.sql', //путь от корня проекта к файлу с дампом базы
    //список таблиц которфе нужно оставить заполненными
    'inserts_array' => [
        'edi_custom_error',
        'edi_gdsn_error',                
        'info_additional_trade_item_identification',
        'info_brand_name',
        'info_functional_name_text',
        'info_gpc',
        'info_gpc_brick_content_type',
        'info_gpc_classification',
        'info_gpc_dec_2012',
        'info_gpc_relation_classification',
        'info_group_units_of_measurement',
        'info_iso3166_1',
        'info_iso3166_2',
        'info_level_of_containment',
        'info_packaging_material',
        'info_packaging_type',
        'info_pallet_terms_and_conditions',
        'info_pallet_type',
        'info_product_allow_bar_code_type',
        'info_product_unit_descriptor',
        'info_promotional_type',
        'info_relation_measure_group',
        'info_ukr_monopoly',
        'info_ukr_statistic',
        'info_ukr_zed',
        'info_units_of_measurement',
        'mapping_brand_name',
        'mapping_classification_category',
        'mapping_client_contractors',
        'product_validation_rules'
    ],
);

