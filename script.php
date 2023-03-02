<?php
//highload
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;
use Bitrix\Main\SystemException;

CModule::IncludeModule('highloadblock');

$highloadItems = [];

$hlblock = HLBT::getList([
    'filter' => ['=TABLE_NAME' => "source"]
])->fetch();

try {
    if (!$hlblock) {
        throw new SystemException("Error: Highload-блок не найден");
    }
} catch (SystemException $exception) {
    echo $exception->getMessage();
}

if ($hlblock) {
    $highloadId = $hlblock["ID"];

    $hlblock = HLBT::getById($highloadId)->fetch();
    $entity = HLBT::compileEntity($hlblock);

    $entity_data_class = $entity->getDataClass();
    $rsData = $entity_data_class::getList([
        'order' => [],
        'select' => ['*'],
        'filter' => ['!UF_ACTIVITY' => 'N']
    ]);


    while ($el = $rsData->fetch()) {
        $highloadItems[$el["UF_CODE"]] = $el;
    }
}
//highload

//iblock
$iblockItems = [];
$res = CIBlockElement::GetList(
    [],
    [
        "IBLOCK_CODE" => "regular",
    ],
    false,
    false,
    [
        "ID",
        "IBLOCK_ID",
        "NAME",
        "PROPERTY_CODE",
        "PROPERTY_ACTIVITY",
        "PROPERTY_PRICE",
        "PROPERTY_FILE",
        "PROPERTY_CITY",
        "PROPERTY_CODES"
    ]
);

while ($ob = $res->GetNextElement()) {
    $arFields = $ob->GetFields();
    $iblockItems[$arFields["PROPERTY_CODE_VALUE"]] = $arFields;
}
//iblock

/*Получаем коды которые существуют в свойстве списка кодов инфоблока*/
$codesOnIblock = [];
$codesOnIblockGet = CIBlockPropertyEnum::GetList(
    [],
    [
        "IBLOCK_CODE" => "regular",
        "CODE" => "CODES"
    ]
);
while ($item = $codesOnIblockGet->GetNext()) {
    $codesOnIblock[$item["ID"]] = $item["VALUE"];
}
/*Получаем коды которые существуют в свойстве списка кодов инфоблока*/

/*Получаем ID свойства списка кодов*/
$resCodes = CIBlockProperty::GetList(
    [],
    [
        'IBLOCK_СODE' => "regular",
        'CODE' => 'CODES',
    ]
);
$field = $resCodes->Fetch();
$propCodesId = $field["ID"];
/*Получаем ID свойства списка кодов*/

/*Добавление кода в список*/
$arCodesOnList = [];
foreach ($highloadItems as $v) {
    if (!in_array($v["UF_CODE"], $codesOnIblock)) {
        $arCodesOnList[] = $v["UF_CODE"];
    }
}

if (!empty($arCodesOnList)) {
    $ibpenum = new CIBlockPropertyEnum;
    foreach ($arCodesOnList as $id) {
        if ($PropID = $ibpenum->Add(
            [
                'PROPERTY_ID' => $propCodesId,
                'VALUE' => $id
            ]
        )
        ) ;
    }

    $codesOnIblockGet = CIBlockPropertyEnum::GetList(
        [],
        [
            "IBLOCK_CODE" => "regular",
            "CODE" => "CODES"
        ]
    );

    while ($item = $codesOnIblockGet->GetNext()) {
        $codesOnIblock[$item["ID"]] = $item["VALUE"];
    }
}
/*Добавление кода в список*/

/*Перебор массивов*/
$arForUpdate = [];
$arAddOnIblock = [];

foreach ($highloadItems as $v) {
    if (array_key_exists($v["UF_CODE"], $iblockItems)) {
        $arForUpdate[] = $v["UF_CODE"];
    } else {
        $arAddOnIblock[] = $v["UF_CODE"];
    }
}
/*Перебор массивов*/

/*Получаем города из свойства инфоблока CITY*/
$arCities = [];
$citiesList = CIBlockPropertyEnum::GetList(array(), array("IBLOCK_CODE" => "regular", "CODE" => "CITY"));
while ($city = $citiesList->GetNext()) {
    $arCities[$city["VALUE"]] = $city["ID"];
}
/*Получаем города из свойства инфоблока CITY*/

/*Обновление элементов*/
$updatedCount = 0;
if (!empty($arForUpdate)) {
    foreach ($arForUpdate as $arItem) {
        $el = new CIBlockElement;
        /*Получение значения элемента списка пользовательского свойства UF_CITY*/
        $rsEnum = CUserFieldEnum::GetList(
            [],
            [
                "USER_FIELD_NAME" => "UF_CITY",
                "ID" => $highloadItems[$arItem]["UF_CITY"]
            ]
        );
        $arEnum = $rsEnum->GetNext();
        /*Получение значения элемента списка пользовательского свойства UF_CITY*/

        $keyCodes = array_search($highloadItems[$arItem]["UF_CODE"], $codesOnIblock);

        $arLoadProduct = [
            "PROPERTY_VALUES" => [
                "CODE" => $highloadItems[$arItem]["UF_CODE"],
                "ACTIVITY" => $highloadItems[$arItem]["UF_ACTIVITY"],
                "PRICE" => $highloadItems[$arItem]["UF_PRICE"],
                "FILE" => $highloadItems[$arItem]["UF_FILE"],
                "CITY" => $arCities[$arEnum["VALUE"]],
                "CODES" => $keyCodes
            ],
        ];

        try {
            if (!$el->Update($iblockItems[$arItem]["ID"], $arLoadProduct)) {
                throw new SystemException("Error: Не удалось обновить элемент c Id = " . $iblockItems[$arItem]["ID"] . "<br>");
            } else {
                $updatedCount++;
            }
        } catch (SystemException $exception) {
            echo $exception->getMessage();
        }
    }
}
/*Обновление элементов*/

/*Добавление элементов*/
$addedCount = 0;
if (!empty($arAddOnIblock)) {
    foreach ($arAddOnIblock as $arItem) {
        $el = new CIBlockElement;

        $rsEnum = CUserFieldEnum::GetList(
            [],
            [
                "USER_FIELD_NAME" => "UF_CITY",
                "ID" => $highloadItems[$arItem]["UF_CITY"]
            ]
        );
        $arEnum = $rsEnum->GetNext();

        $keyCodes = array_search($highloadItems[$arItem]["UF_CODE"], $codesOnIblock);

        $arLoadProduct = array(
            "MODIFIED_BY" => $USER->GetID(),
            "IBLOCK_SECTION_ID" => false,
            "IBLOCK_ID" => reset($iblockItems)["IBLOCK_ID"],
            "PROPERTY_VALUES" => [
                "CODE" => $highloadItems[$arItem]["UF_CODE"],
                "ACTIVITY" => $highloadItems[$arItem]["UF_ACTIVITY"],
                "PRICE" => $highloadItems[$arItem]["UF_PRICE"],
                "FILE" => $highloadItems[$arItem]["UF_FILE"],
                "CITY" => $arCities[$arEnum["VALUE"]],
                "CODES" => $keyCodes
            ],
            "NAME" => $highloadItems[$arItem]["UF_CODE"],
            "ACTIVE" => $highloadItems[$arItem]["UF_ACTIVITY"],
        );

        if ($el->Add($arLoadProduct)) {
            $addedCount++;
        } else {
            echo "Error: " . $el->LAST_ERROR;
            break;
        }
    }
}
/*Добавление элементов*/

if ($updatedCount > 0) {
    echo "Обновлено элементов: " . $updatedCount . "<br>";
}

if ($addedCount > 0) {
    echo "Добавлено элементов: " . $addedCount;
}
