<?php

namespace App\Support;

class SurveyEvidenceCategories
{
    /** @var array<string, array<string, string>> */
    public const ALL = [
        'offshore-helideck' => [
            'vhf-and-hand-held-radios' => 'VHF and HAND HELD RADIOS',
            'calibrated-weighing-scale' => 'CALIBRATED WEIGHING SCALE',
            'briefing-room' => 'BRIEFING ROOM',
            'wind-sock-with-illumintion' => 'WIND SOCK WITH ILLUMINTION',
            'helicopter-crash-box' => 'HELICOPTER CRASH BOX',
            'ane-mometer-and-inclinometer' => 'ANE MOMETER AND INCLINOMETER',
            'sealed-helideck-with-air-gap' => 'SEALED HELIDECK WITH AIR GAP',
            'lifeboat' => 'LIFEBOAT',
            'top-view' => 'TOP VIEW',
            'afff-foam-and-wate-r-hose-station' => 'AFFF FOAM AND WATE R HOSE STATION',
            'good-helideck-lighting' => 'GOOD HELIDECK LIGHTING',
            'primeter-safety-net-and-light' => 'PRIMETER SAFETY NET AND LIGHT',
        ],
        'onshore-heliport' => [
            'crash-box' => 'CRASH BOX',
            'fire-fighting-system' => 'FIRE FIGHTING SYSTEM',
            'aff-system' => 'AFF SYSTEM',
        ],
    ];
}
