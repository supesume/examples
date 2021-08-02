<?php
require_once '/var/www/oneteam/one-team.ru/kernel/public.php';

///
define('FED_PROGRAMS', true);
define('ADDITIONAL_REINF', false);
///

$young = new c_young(999);
// Максимальное количество обучающихся в ШЮ по уровням
$maxYoungs = [
    1 => 9,
    2 => 11,
    3 => 13,
    4 => 15,
    5 => 17,
    6 => 19
];
// Количество юниоров на зачисление по уровням ШЮ
$youngsByLevel = $young::YOUNG_BY_LEVEL;

// Вероятности по которым будем генерировать страну
$countryChance = [80, 15, 5];
// Позиции по линиям, используется в генерации позиции
$positionByLine = [
    1 => ["GK"],
    2 => ["SW", "LD", "CD", "RD"],
    3 => ["LM", "DM", "RM", "CM"],
    4 => ["LW", "AM", "RW"],
    5 => ["CF", "LF", "RF"]
];
// Вероятности генерации возраста в зависимости от уровня ШЮ
$ageChance = [
    1 => [16 => 10, 17 => 15, 18 => 40, 19 => 35],
    2 => [16 => 15, 17 => 20, 18 => 35, 19 => 30],
    3 => [16 => 20, 17 => 25, 18 => 30, 19 => 25],
    4 => [16 => 25, 17 => 30, 18 => 25, 19 => 20],
    5 => [16 => 30, 17 => 35, 18 => 20, 19 => 15],
    6 => [16 => 35, 17 => 40, 18 => 15, 19 => 10]
];
// Позиции для развития определённых групп навыков
$positionBySkills = $young::POSITION_BY_SKILLS;
// Навыки для развития по группам (зависимость от positionBySkills)
$skillsByGroup = $young::SKILLS_BY_GROUP;
// Вероятность генерации навыков по группам (зависимость от positionBySkills)
$skillsChance = [
    1 => [
        1 => [11, 13],
        2 => [3, 5],
        3 => [1, 3]
    ],
    2 => [
        1 => [12, 14],
        2 => [4, 6],
        3 => [2, 4]
    ],
    3 => [
        1 => [13, 15],
        2 => [5, 7],
        3 => [3, 5]
    ],
    4 => [
        1 => [14, 16],
        2 => [6, 8],
        3 => [4, 6]
    ],
    5 => [
        1 => [15, 17],
        2 => [7, 9],
        3 => [5, 7]
    ],
    6 => [
        1 => [16, 18],
        2 => [8, 10],
        3 => [6, 8]
    ]
];
// Позиции для генерации определённых групп талантов
$positionByTalents = $young::POSITION_BY_TALENTS;
// Таланты для развития по группам (зависимость от positionByTalents)
// !!! Оказывается просто так перегруппировать эти таланты нельзя (или удалить/добавить), скрипт упадёт.
$talentsByGroup = $young::TALENTS_BY_GROUP;
// Дефекты 1 - для вратаря, 2 - для остальных
$defects = [
    1 => ["TalentStamina", "TalentProgress", "Ambition", "TalentLeader"],
    2 => ["TalentStamina", "TalentProgress", "Ambition", "TalentLeader", "TalentSimulator", "TalentKick"]
];
// Лимит добора игроков по программе №13 за ОВП
$limitProgram13 = [
    1 => 3,
    2 => 4,
    3 => 4,
    4 => 5,
    5 => 5,
    6 => 6
];

/*
    Распределяем вероятности для генерации уровня рабочих талантов.
    Чем больше показатель таланта, тем меньше вероятность его выпадения. При чём шаг уменьшения вероятности должен тоже уменьшаться.
    Для этих условий идеально подходит фунция параболы. Функция была представлена графически, где: ось абцисс (x) - вероятности возникновения показателя, ось ординат (y) - показатели. Функция имеет вид: y = a*x^2 + b*x + c.
    По двум имеющимся точкам и их координатам ((0.2, 90) и (40, 44)) были найдены коэффициенты a,b и c. Вершина параболы - (40, 44). Таким образом было получено уравнение параболы. Подставляя значения показателей (y), решаем квадратное уравнение, находим меньший корень, именно он и будет вероятностью показателя.
    Условие уменьшения шага соблюдается.
*/
$workTalentsChance = [];
$a = 46 / 1584.04;
$b = -80 * $a;
$c = 44 - 40 * 40 * $a - 40 * $b;
$workTalentsChance[44] = 40;
for ($i = 45; $i <= 89; $i++) {
    $d = sqrt($b * $b - 4 * $a * ($c - $i));
    $x = (-$b - $d) / (2 * $a);
    $workTalentsChance[$i] = $x;
}
$workTalentsChance[90] = 0.2;

/*
    Распределяем вероятности для генерации par_tactic.
    Аналогично с рабочими талантами.
*/
$parTacticChance = [];
$a = 60 / 380.25;
$b = -40 * $a;
$c = 20 - 20 * 20 * $a - 20 * $b;
$parTacticChance[20] = 20;
for ($i = 21; $i <= 79; $i++) {
    $d = sqrt($b * $b - 4 * $a * ($c - $i));
    $x = (-$b - $d) / (2 * $a);
    $parTacticChance[$i] = $x;
}
$parTacticChance[80] = 0.5;

/*
    Если первое число месяца, то зачисляем во все школы,
    Иначе зачисляем только в школы первого уровня, которые были построены сутки назад (если такие имеются)
*/
$res = 0;
//if (date('d') == "01" || date('l') == 'Sunday' ) {
if (date('d') == "01" || ADDITIONAL_REINF) {
    $res = $young->query('SELECT x1.*, c.Zone, c.Name CName, SUM(IF(p.Young = 1, 1, 0)) SumCurrent, 
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[1]) . '") AND p.Young = 1, 2, 0)) Line1,
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[2]) . '") AND p.Young = 1, 1, 0)) Line2,
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[3]) . '") AND p.Young = 1, 1, 0)) Line3,
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[4]) . '") AND p.Young = 1, 1, 0)) Line4,
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[5]) . '") AND p.Young = 1, 1, 0)) Line5
                          FROM (
                            SELECT tt.*, IF(t.FederationID is NULL, t.CountryID, t.FederationID) as CountryID, ut.UserID, t.Name TName
                            FROM (
                            SELECT tb.TeamId, Max(b.Level) AS YCLevel, tb.DayBuild
                                    FROM OT_TeamBuildings tb, OT_Buildings b
                                    WHERE b.ParentId = 4 AND b.BuildId = tb.BuildId AND tb.Status = 1
                                    GROUP BY tb.TeamId
                                    ORDER BY YCLevel DESC
                            ) AS tt, OT_Team t, OT_UserTeam ut
                            WHERE t.TeamID = tt.TeamId AND ut.TeamID = t.TeamId
                          ) AS x1, OT_PlayerTeam pt, OT_Players p, OT_Country c
                          WHERE pt.TeamID = x1.TeamId AND p.PlayerID = pt.PlayerID AND c.CountryID = x1.CountryID
                          GROUP BY x1.TeamId
                        ORDER BY YCLevel DESC');
} else {
    $res = $young->query('SELECT x1.*, c.Zone, c.Name CName, SUM(IF(p.Young = 1, 1, 0)) SumCurrent, 
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[1]) . '") AND p.Young = 1, 2, 0)) Line1,
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[2]) . '") AND p.Young = 1, 1, 0)) Line2,
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[3]) . '") AND p.Young = 1, 1, 0)) Line3,
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[4]) . '") AND p.Young = 1, 1, 0)) Line4,
                            SUM(IF(p.Position IN ("' . implode('","', $positionByLine[5]) . '") AND p.Young = 1, 1, 0)) Line5
                          FROM (
                            SELECT tt.*, IF(t.FederationID is NULL, t.CountryID, t.FederationID) as CountryID, ut.UserID, t.Name TName
                            FROM (
                            SELECT tb.TeamId, Max(b.Level) AS YCLevel, tb.DayBuild
                                    FROM OT_TeamBuildings tb, OT_Buildings b
                                    WHERE b.BuildId = 13 AND b.BuildId = tb.BuildId AND tb.Status = 1 AND (\'' . $young->_options['day'] . '\' - tb.DayBuild = 2) 
                                    GROUP BY tb.TeamId
                                    ORDER BY YCLevel DESC
                            ) AS tt, OT_Team t, OT_UserTeam ut
                            WHERE t.TeamID = tt.TeamId AND ut.TeamID = t.TeamId
                          ) AS x1, OT_PlayerTeam pt, OT_Players p, OT_Country c
                          WHERE pt.TeamID = x1.TeamId AND p.PlayerID = pt.PlayerID AND c.CountryID = x1.CountryID
                          GROUP BY x1.TeamId
                        ORDER BY YCLevel DESC');
}

$text = 'No new youngs';
if ($res->num_rows != 0) {
    // Дополнительная проверка от повторного запуска скрипта
    $checkStart = $young->query('SELECT DateUpdated 
                            FROM OT_SiteOptions 
                            WHERE OptionName = "young_script" 
                                AND (DateUpdated < (NOW() - INTERVAL 1 DAY) OR DateUpdated is NULL)');
    if ($checkStart->num_rows != 0) {
        $totalYoungs = $totalShools = 0;
        while ($row = $res->fetch_assoc()) {
            /*
                Если в этом месяце были зачисления за построенную ШЮ 1 уровня и ШЮ так и осталась первого уровня, то ежемесячные зачисления считаются выполненными
            */
//            if (date('d') == "01" && $row['YCLevel'] == 1 && $row['DayBuild'] > ($young->_options['day'] - 60)) {
//                continue;
//            }

            $fedPrograms = FED_PROGRAMS ? $young->VerifFedPrograms($row['TeamId']) : [];
            $youngDayPeriod = in_array(92, $fedPrograms) ? 140 : 120;
            $countNew = $countAdd = 0;
            // Количество юниоров по линиям (для генерации позиции)
            $lines = [
                1 => $row['Line1'],
                2 => $row['Line2'],
                3 => $row['Line3'],
                4 => $row['Line4'],
                5 => $row['Line5']
            ];
            $program7 = 0;
            if (in_array(7, $fedPrograms)) {
                $program7 = 1;
            }
            // Количество юниоров на зачисление не должно превышать лимита обучающихся
            if ($maxYoungs[$row['YCLevel']] - $row['SumCurrent'] < $youngsByLevel[$row['YCLevel']] + $program7) {
                $countNew = $maxYoungs[$row['YCLevel']] - $row['SumCurrent'];
            } else {
                $countNew = $youngsByLevel[$row['YCLevel']] + $program7;
            }

            /*
            if (date('l') == 'Sunday' && date('d') != "01") {
                $staffProgram = $young->VerifStaff($row['TeamId'], [13]);
                $chanceProgram13 = [40, 60];
                $resultProgram13 = $young->customRandom($chanceProgram13);
                if (
                    $resultProgram13 == 0
                    && $row['SumCurrent'] <= $limitProgram13[$row['YCLevel']]
                    && in_array(13, $staffProgram)
                    && !($row['YCLevel'] == 1 && ($young->_options['day'] - $row['DayBuild'] == 2))
                ) {
                    $countNew = 1
                } elseif (!($row['YCLevel'] == 1 && ($young->_options['day'] - $row['DayBuild'] == 2))) {
                    continue;
                }
            }
            */

            $totalYoungs += $countNew;
            $totalShools += 1;
            $newYoungs = '';
            // Генерируем и добавляем юниоров
            while ($countAdd < $countNew) {
                $country = $age = 0;
                $position = $name = $countryName = '';
                /*
                    Если есть позиция и страна из приоритета, то используем эти значения
                    Иначе генерируем случайно
                */
                $res1 = $young->query('SELECT py.RecordId, py.Position, py.CountryId, c.Name
                                        FROM OT_PlayersYoungPriority py
                                            LEFT JOIN OT_Country c ON (c.CountryID = py.CountryId)
                                        WHERE TeamId = ' . $row['TeamId'] . '                                            
                                            AND Status = 0
                                        ORDER BY Priority
                                        LIMIT 1');

                if ($res1->num_rows != 0) {
                    $row1 = $res1->fetch_assoc();
                    $country = $row1['CountryId'];
                    $position = $row1['Position'];
                    $countryName = $row1['Name'];
                    // Отмечяем, что данные значения использованы
                    $young->query('UPDATE OT_PlayersYoungPriority
                                SET Status = 1, DateUsed = NOW(), Counter = Counter + 1
                                WHERE RecordId = ' . $row1['RecordId']);
                } else {
                    /*
                    Выбираем позицию
                    */
                    // Находим линию, в которой меньше всего игроков на обучении
                    $lowLines = array_keys($lines, min($lines));
                    $lowLine = $lowLines[array_rand($lowLines)];

                    // Выбираем случайную позицию из этой линии
                    $randomPosition = array_rand($positionByLine[$lowLine], 1);
                    $position = $positionByLine[$lowLine][$randomPosition];
                    // Обновляем значение
                    $lines[$lowLine]++;

                    /*
                        Выбираем страну
                        Шанс выпадения:
                            страна клуба - 80%
                            страна конфедерации - 15%
                            из остальных стран - 5%
                    */
                    $result = $young->customRandom($countryChance);

                    if ($result == 0) {
                        $country = $row['CountryID'];
                        $countryName = $row['CName'];
                    } elseif ($result == 1) {
                        $res_c = $young->query('SELECT CountryID, Name 
                                                    FROM OT_Country
                                                    WHERE Zone = ' . $row['Zone'] . '
                                                        AND CountryID != ' . $row['CountryID'] . '
                                                        AND IsNational=1
                                                    ORDER BY RAND()
                                                    LIMIT 1');

                        $row_c = $res_c->fetch_assoc();
                        $country = $row_c['CountryID'];
                        $countryName = $row_c['Name'];
                    } else {
                        $res_c = $young->query('SELECT CountryID, Name 
                                                    FROM OT_Country
                                                    WHERE Zone != ' . $row['Zone'] . ' AND IsNational=1
                                                    ORDER BY RAND()
                                                    LIMIT 1');

                        $row_c = $res_c->fetch_assoc();
                        $country = $row_c['CountryID'];
                        $countryName = $row_c['Name'];
                    }
                }

                /*
                    Генерация имени
                */
                $x = 0;
                $resN = $young->query('SELECT PlayerID, Name
                                        FROM OT_Players
                                        WHERE CountryID = ' . $country . ' AND Name LIKE "% %"
                                        ORDER BY RAND()
                                        LIMIT 2');
                while ($rowN = $resN->fetch_assoc()) {
                    if ($x == 0) {
                        $temp = explode(' ', $rowN['Name']);
                        $name .= trim($temp[0]);
                    } else {
                        $temp = explode(' ', $rowN['Name'], 2);
                        $name .= ' ' . trim($temp[1]);
                    }
                    $x++;
                }

                /*
                    Генерация возраста
                */
                $age = $young->customRandom($ageChance[$row['YCLevel']]);

                /*
                    Генерация базового уровня навыков
                */
                // Находим по позиции какие навыки будут генерироваться
                $skillsPos = 0;
                foreach ($positionBySkills as $key => $value) {
                    if (array_search($position, $value) !== false) {
                        $skillsPos = $key;
                        break;
                    }
                }
                // Заполняем уровни навыков по группам
                $allSkills = [];
                for ($i = 1; $i <= 3; $i++) {
                    $skills = [];
                    for ($j = 0; $j < count($skillsByGroup[$skillsPos][$i]); $j++) {
                        $skill = mt_rand($skillsChance[$row['YCLevel']][$i][0], $skillsChance[$row['YCLevel']][$i][1]);
                        array_push($skills, $skill);
                    }
                    $allSkills[$i] = $skills;
                }

                /*
                    Генерация талантов
                */
                $talentsPos = $talent1 = $talent2 = $talent3 = 0;
                // Находим по позиции какие таланты будем генерировать
                foreach ($positionByTalents as $key => $value) {
                    if (array_search($position, $value) !== false) {
                        $talentsPos = $key;
                        break;
                    }
                }
                // Определяем тип позиции
                $typePos = '';
                switch ($talentsPos) {
                    case 1:
                        $typePos = "GK";
                        break;
                    case 2:
                        $typePos = "DEF";
                        break;
                    case 3:
                        $typePos = "MID";
                        break;
                    case 4:
                        $typePos = "ATTMID";
                        break;
                    case 5:
                        $typePos = "ATT";
                        break;
                }
                // Находим модификатор
                $modif = 0;
                $resModif = $young->query('SELECT ' . $typePos . ' 
                                        FROM OT_TeamSchool 
                                        WHERE TeamId = ' . $row['TeamId']);
                if ($resModif->num_rows != 0) {
                    $modif = $resModif->fetch_assoc()[$typePos];
                }

                $bt1 = $bt2 = $bt3 = 0;
                if (in_array(94, $fedPrograms)) {
                    $bt1 = 5;
                    $bt2 = 4;
                    $bt3 = 3;
                }

                // Шансы возникновения/невозникновения рабочих талантов по группам
                $chanceGroup1 = [5 + $bt1 + $row['YCLevel'] + $modif / 40, 100 - (5 + $row['YCLevel'] + $modif / 40)];
                $chanceGroup2 = [3 + $bt2 + $row['YCLevel'] * 0.5 + $modif / 80, 100 - (3 + $row['YCLevel'] * 0.5  + $modif / 80)];
                $chanceGroup3 = [1 + $bt3 + $row['YCLevel'] * 0.25 + $modif / 120, 100 - (1 + $row['YCLevel'] * 0.25 + $modif / 120)];
                // Заполняем уровни талантов по группам
                $allTalents = [];
                for ($i = 1; $i <= 3; $i++) {
                    $talents = [];
                    for ($j = 0; $j < count($talentsByGroup[$talentsPos][$i]); $j++) {
                        $talent = mt_rand(24, 38);
                        array_push($talents, $talent);
                    }
                    $allTalents[$i] = $talents;
                }

                // Пытаем удачу сгенерировать рабочие таланты группы 1
                $chanceTalent = $young->customRandom($chanceGroup1);
                // Если получилось сгененировать, выбираем случайный талант группы и гененируем значение
                if ($chanceTalent == 0) {
                    $randomTalent = array_rand($allTalents[1], 1);
                    $talentValue = $young->customRandom($workTalentsChance);
                    $allTalents[1][$randomTalent] = $talentValue;
                    $talent1 = 1;
                }

                // Пытаем удачу сгенерировать рабочие таланты группы 2
                $chanceTalent = $young->customRandom($chanceGroup2);
                // Если получилось сгененировать, выбираем случайный талант группы и гененируем значение
                if ($chanceTalent == 0) {
                    $randomTalent = array_rand($allTalents[2], 1);
                    $talentValue = $young->customRandom($workTalentsChance);
                    $allTalents[2][$randomTalent] = $talentValue;
                    $talent2 = 1;
                }

                // Пытаем удачу сгенерировать рабочие таланты группы 3
                $chanceTalent = $young->customRandom($chanceGroup3);
                // Если получилось сгененировать, выбираем случайный талант группы и гененируем значение
                if ($chanceTalent == 0 && $position != "GK") {
                    $randomTalent = array_rand($allTalents[3], 1);
                    $talentValue = $young->customRandom($workTalentsChance);
                    $allTalents[3][$randomTalent] = $talentValue;
                    $talent3 = 1;
                }

                /*
                    Дефекты
                */
                // Шансы выпадения дефектов
                $chanceDefect1 = [4 - ($row['YCLevel'] * 0.5), 100 - (4 - ($row['YCLevel'] * 0.5))];
                $chanceDefect2 = [2 - ($row['YCLevel'] * 0.25), 100 - (2 - ($row['YCLevel'] * 0.25))];
                // Пытаем неудачу сгенерировать первый дефект
                $chanceDefect = $young->customRandom($chanceDefect1);
                /*
                    В зависимости от позиции выбираем какой талант будет с дефектом.
                    Учитываем, что у каких-то позиций нет определённых талантов и то, что эти таланты могут относиться к разным группам талантов у каждой группы позиций.
                */
                $defect1 = -1;
                if ($chanceDefect == 0) {
                    if ($position == "GK") {
                        $defect1 = mt_rand(0, 3);
                        $allTalents[2][$defect1] = mt_rand(1, 20);
                    } elseif (in_array($position, $positionByTalents[3])) {
                        $defect1 = mt_rand(0, 5);
                        if ($defect1 < 4) {
                            $allTalents[2][$defect1] = mt_rand(1, 20);
                        } elseif ($defect1 == 4) {
                            $allTalents[3][0] = mt_rand(1, 20);
                        } else {
                            $allTalents[3][1] = mt_rand(1, 20);
                        }
                    } elseif (in_array($position, $positionByTalents[5])) {
                        while ($defect1 == -1 || $defect1 == 4) {
                            $defect1 = mt_rand(0, 5);
                        }
                        if ($defect1 == 5) {
                            $allTalents[1][0] = mt_rand(1, 20);
                        } else {
                            $allTalents[2][$defect1] = mt_rand(1, 20);
                        }
                    } else {
                        while ($defect1 == -1 || $defect1 == 4) {
                            $defect1 = mt_rand(0, 5);
                        }
                        if ($defect1 == 5) {
                            $allTalents[3][0] = mt_rand(1, 20);
                        } else {
                            $allTalents[2][$defect1] = mt_rand(1, 20);
                        }
                    }
                }

                // Пытаем неудачу сгенерировать второй дефект
                $chanceDefect = $young->customRandom($chanceDefect2);
                /*
                    Учитываем всё то же, что и в первом дефекте. Плюс: если выпал первый дефект, то второй дефект не может выпасть на тот же талант.
                */
                $defect2 = -1;
                if ($chanceDefect == 0) {
                    if ($position == "GK") {
                        while ($defect2 == -1 || $defect2 == $defect1) {
                            $defect2 = mt_rand(0, 3);
                        }
                        $allTalents[2][$defect2] = mt_rand(1, 20);
                    } elseif (in_array($position, $positionByTalents[3])) {
                        while ($defect2 == -1 || $defect2 == $defect1) {
                            $defect2 = mt_rand(0, 5);
                        }
                        if ($defect2 < 4) {
                            $allTalents[2][$defect2] = mt_rand(1, 20);
                        } elseif ($defect2 == 4) {
                            $allTalents[3][0] = mt_rand(1, 20);
                        } else {
                            $allTalents[3][1] = mt_rand(1, 20);
                        }
                    } elseif (in_array($position, $positionByTalents[5])) {
                        while ($defect2 == -1 || $defect2 == 4 || $defect2 == $defect1) {
                            $defect2 = mt_rand(0, 5);
                        }
                        if ($defect2 == 5) {
                            $allTalents[1][0] = mt_rand(1, 20);
                        } else {
                            $allTalents[2][$defect2] = mt_rand(1, 20);
                        }
                    } else {
                        while ($defect2 == -1 || $defect2 == 4 || $defect2 == $defect1) {
                            $defect2 = mt_rand(0, 5);
                        }
                        if ($defect2 == 5) {
                            $allTalents[3][0] = mt_rand(1, 20);
                        } else {
                            $allTalents[2][$defect2] = mt_rand(1, 20);
                        }
                    }
                }

                /*
                    Генерация par_tactic
                */
                $par_tactic = $young->customRandom($parTacticChance);
                // Обновляем таблицу OT_TeamSchool
                $defect1 = $defect1 == -1 ? 0 : 1;
                $defect2 = $defect2 == -1 ? 0 : 1;

                if ($defect1 == 0 && $defect2 == 0 && $talent1 == 0 && $talent2 == 0 && $talent3 == 0) {
                    $young->query('INSERT INTO OT_TeamSchool (TeamId, DateLastEnrollment, ' . $typePos . ') 
                                    VALUES (' . $row['TeamId'] . ', NOW(), ' . $typePos . ' + 20) 
                                    ON DUPLICATE KEY UPDATE ' . $typePos . ' = ' . $typePos . ' + 20, DateLastEnrollment = NOW()');
                } else {
                    $calc1 = (100 * $talent1) + (40 * $talent2) + (40 * $talent3);
                    $calc2 = (80 * $defect1) + (80 * $defect2);
                    $young->query('INSERT INTO OT_TeamSchool (TeamId, DateLastEnrollment, ' . $typePos . ') 
                                    VALUES (' . $row['TeamId'] . ', NOW(), ' . $typePos . '-' . $calc1 . '+' . $calc2 . ') 
                                    ON DUPLICATE KEY UPDATE ' . $typePos . ' = ' . $typePos . '-' . $calc1 . '+' . $calc2 . ', DateLastEnrollment = NOW()');
                }

                // Добавляем юниора
                $young->query('INSERT INTO OT_Players (Young, YoungDayPeriod, IsGenGen, CountryID, Name, Position, Age, DateAging, DateCreate, par_tactic,  
                    ' . implode(', ', $skillsByGroup[$skillsPos][1]) . ', 
                    ' . implode(', ', $skillsByGroup[$skillsPos][2]) . ', 
                    ' . implode(', ', $skillsByGroup[$skillsPos][3]) . ', 
                    ' . implode(', ', $talentsByGroup[$talentsPos][1]) . ', 
                    ' . implode(', ', $talentsByGroup[$talentsPos][2]) . ', 
                    ' . implode(', ', $talentsByGroup[$talentsPos][3]) . ')
                                    VALUES (1, ' . $youngDayPeriod . ', 1, ' . $country . ', "' . addslashes(stripslashes($name)) . '", "' . $position . '", ' . $age . ', NOW(), NOW(), ' . $par_tactic . ',   
                                    ' . implode(', ', $allSkills[1]) . ', 
                                    ' . implode(', ', $allSkills[2]) . ', 
                                    ' . implode(', ', $allSkills[3]) . ', 
                                    ' . implode(', ', $allTalents[1]) . ', 
                                    ' . implode(', ', $allTalents[2]) . ', 
                                    ' . implode(', ', $allTalents[3]) . ')');

                $playerId = $young->insert_id;

                ////////////////////////////////////////////////////////////////////////////////////////////////////////
                /// Различные бонусы при зачислении
                $talentPerm = $bonus = [];
                $talentsGroup1 = $talentsByGroup[$talentsPos][1];
                $talentsGroup2 = $talentsByGroup[$talentsPos][2];
                $talentsGroup3 = $talentsByGroup[$talentsPos][3];

                /*
                    Открываем 2 таланта, если активна программа федерации №2
                    Добавляем +2 к таланту за его открытие (т.к. игрок только что сгенерирован, открытие точно будет первым)
                */
                if (in_array(2, $fedPrograms)) {
                    $randomTalentKey1 = array_rand($talentsGroup1, 1);
                    $randomTalent1 = $talentsGroup1[$randomTalentKey1];
                    unset($talentsGroup1[$randomTalentKey1]); // Чтобы не дублировать в следующем бонусе

                    $randomTalentKey2 = array_rand($talentsGroup2, 1);
                    $randomTalent2 = $talentsGroup2[$randomTalentKey2];
                    unset($talentsGroup2[$randomTalentKey2]); // Чтобы не дублировать в следующем бонусе

                    $bonus[$randomTalent1] = 2;
                    $bonus[$randomTalent2] = 2;
                }
                // 50% вероятность открытия 1 таланта. С 1 уровня школы
                if (mt_rand(0, 100) < 50) {
                    if ($row['YCLevel'] >= 4) {
                        $tg = $talentsGroup1;
                    } elseif ($row['YCLevel'] >= 3) {
                        $tg = array_merge($talentsGroup1, $talentsGroup2);
                    } else {
                        $tg = array_merge($talentsGroup1, $talentsGroup2, $talentsGroup3);
                    }

                    $randomTalentKey3 = array_rand($tg, 1);
                    $randomTalent3 = $tg[$randomTalentKey3];
                    $bonus[$randomTalent3] = 2;
                }

                if (!empty($bonus)) {
                    $query = [];
                    foreach ($bonus as $talentAlias => $talentUp) {
                        $talentPerm[$talentAlias] = [$row['UserID']];
                        $query[] = $talentAlias . '=' . $talentAlias . '+2';
                    }

                    $bonus['type'] = 'young';
                    $bonus = addslashes(serialize($bonus));
                    $young->query('INSERT INTO OT_PlayerStat (PlayerId, TeamId, UpdateParam, Day) VALUES (' . $playerId . ', ' . $row['TeamId'] . ', "' . $bonus . '", ' . $young->_options['day'] . ')');
                    $young->query('UPDATE OT_Players 
                                    SET ' . implode(', ', $query) . ',
                                        TalentPerm = "' . addslashes(serialize($talentPerm)) . '"
                                    WHERE PlayerId = ' . $playerId);
                }


                // Связь команда <=> игрок
                $young->query('INSERT INTO OT_PlayerTeam (TeamID, PlayerID) 
                                VALUES (' . $row['TeamId'] . ', ' . $playerId . ')');
                // Добавляем запись в OT_PlayersYoung
                $young->query('INSERT INTO OT_PlayersYoung (RealId, TeamId, Name, Position, CountryId, DateAdd, DateSchool, IsModer, LevelS, IsGen) 
                                VALUES (' . $playerId . ', ' . $row['TeamId'] . ', "' . addslashes(stripslashes($name)) . '", "' . $position . '", ' . $country . ', NOW(), NOW(), 1, ' . $row['YCLevel'] . ', 1)');
                // Вносим информацию по игроку в сообщение
                $replace = [
                    '{POSITION}'    => $position,
                    '{PLAYER_ID}'   => $playerId,
                    '{AGE}'         => $age,
                    '{NAME}'        => addslashes(stripslashes($name)),
                    '{NATIONAL}'    => $countryName,
                    '{COUNTRY_ID}'  => $country
                ];
                $newYoungs .= BASEF::Tpl('newYoung', $replace, 'cron/youngs');
                // обновляем количество зачисленных
                $countAdd++;
            }

            // Отправляем письмо клубу со списком зачисленных юниоров
            $young->SendEventNews(BASEF::Tpl('newYoungs', ['{LIST}' => $newYoungs], 'cron/youngs'), $row['TeamId'], '', 'new_young');
        }

        $text = 'Количество зачисленных: ' . $totalYoungs . ', количество школ: ' . $totalShools;
    }

    // Ставим заметку о том, что скрипт прошёл
    $young->query('UPDATE OT_SiteOptions SET DateUpdated = NOW() WHERE OptionName = "young_script"');

    // Отправляем техническое инфо
    $young->SendTrace($text, 'YOUNGS TO SCHOOL!!!');
}
