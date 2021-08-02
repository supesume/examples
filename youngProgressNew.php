<?php
require_once '/var/www/oneteam/one-team.ru/kernel/public.php';

$young = new c_basis();

// Позиции по навыкам
$positionBySkills = [
    1 => ["GK"],
    2 => ["SW", "LD", "CD", "RD"],
    3 => ["DM"],
    4 => ["LM", "CM", "RM"],
    5 => ["LW", "RW"],
    6 => ["AM", "CF", "LF", "RF"]
];
// Навыки для развития по группам (зависимость от positionBySkills)
$skillsByGroup = [
    1 => [
            1 => ["tr_parry", "tr_on_exit", "tr_tactic"],
            2 => ["tr_pass", "tr_Penalty11", "tr_kick"],
            3 => ["tr_Penalty", "tr_sel", "tr_Corner"]
        ],
    2 => [
            1 => ["tr_sel", "tr_pass", "tr_tactic"],
            2 => ["tr_Penalty", "tr_Penalty11", "tr_Corner"],
            3 => ["tr_dribl", "tr_kick"]
        ],
    3 => [
            1 => ["tr_sel", "tr_pass", "tr_tactic"],
            2 => ["tr_Penalty", "tr_Penalty11", "tr_dribl"],
            3 => ["tr_Corner", "tr_kick"]
        ],
    4 => [
            1 => ["tr_pass", "tr_dribl", "tr_tactic"],
            2 => ["tr_sel", "tr_kick", "tr_Corner"],
            3 => ["tr_Penalty11", "tr_Penalty"]
        ],
    5 => [
            1 => ["tr_pass", "tr_dribl", "tr_tactic"],
            2 => ["tr_kick", "tr_Penalty", "tr_Corner"],
            3 => ["tr_sel", "tr_Penalty11"]
        ],
    6 => [
            1 => ["tr_dribl", "tr_kick", "tr_tactic"],
            2 => ["tr_pass", "tr_Penalty", "tr_Penalty11"],
            3 => ["tr_sel", "tr_Corner"]
        ]
];
// Вероятность генерации прогресса навыков по группам (зависимость от уровня ШЮ)
$progressChance = [
    1 => [
            1 => [5, 8],
            2 => [],
            3 => []
        ],
    2 => [
            1 => [7, 10],
            2 => [2, 4],
            3 => []
        ],
    3 => [
            1 => [9, 12],
            2 => [3, 5],
            3 => [1, 3]
        ],
    4 => [
            1 => [11, 13],
            2 => [4, 6],
            3 => [2, 4]
        ],
    5 => [
            1 => [12, 15],
            2 => [5, 7],
            3 => [3, 5]
        ],
    6 => [
            1 => [14, 17],
            2 => [6, 8],
            3 => [4, 6]
        ]
];

// Позиции по талантам
$positionByTalents = [
    1 => ["GK"],
    2 => ["SW", "LD", "CD", "RD", "DM"],
    3 => ["LM", "CM", "RM"],
    4 => ["AM", "LW", "RW"],
    5 => ["CF", "LF", "RF"]
];
// Таланты для развития по группам (зависимость от positionByTalents)
$talentsByGroup = [
    1 => [
            1 => ["TalentReaction", "TalentPosition"],
            2 => ["TalentStamina", "TalentProgress", "Ambition", "TalentLeader"],
            3 => []
        ],
    2 => [
            1 => ["TalentSimulator", "TalentPass"],
            2 => ["TalentStamina", "TalentProgress", "Ambition", "TalentLeader"],
            3 => ["TalentKick"]
        ],
    3 => [
            1 => ["TalentPass", "TalentDribbling"],
            2 => ["TalentStamina", "TalentProgress", "Ambition", "TalentLeader"],
            3 => ["TalentKick", "TalentSimulator"]
        ],
    4 => [
            1 => ["TalentDribbling"],
            2 => ["TalentStamina", "TalentProgress", "Ambition", "TalentLeader"],
            3 => ["TalentKick", "TalentPass"]
        ],
    5 => [
            1 => ["TalentKick"],
            2 => ["TalentStamina", "TalentProgress", "Ambition", "TalentLeader"],
            3 => ["TalentDribbling", "TalentPass"]
        ]
];
// Повышения прайса на обучение в зависимости от уровня школы
$upPrice = [
    1 => 1000,
    2 => 2000,
    3 => 3000,
    4 => 4000,
    5 => 5000,
    6 => 6000
];
// Прогресс <=> подъём
$trToPar = [
    'tr_pass'      => 'par_pass',
    'tr_sel'       => 'par_sel',
    'tr_kick'      => 'par_kick',
    'tr_dribl'     => 'par_dribl',
    'tr_on_exit'   => 'par_on_exit',
    'tr_parry'     => 'par_parry',
    'tr_Corner'    => 'Corner',
    'tr_Penalty'   => 'Penalty',
    'tr_Penalty11' => 'Penalty11',
    'tr_tactic'    => 'par_tactic'
];
// Расшифровка для OT_PreparePlayers
$talentsIco = [
    'TalentProgress'    => 'progress',
    'TalentLeader'      => 'leader',
    'TalentKick'        => 'kick',
    'TalentDribbling'   => 'control',
    'TalentPass'        => 'pass',
    'TalentStamina'     => 'stamina',
    'TalentSimulator'   => 'wall',
    'Ambition'          => 'ambition',
    'TalentPosition'    => 'coord',
    'TalentReaction'    => 'shield'
];
// функция рандомайзер по вероятности
function customRandom ($chances) {
    $max = $result = 0;
    foreach($chances as $key => $value) {
        $rand = pow((mt_rand() / (mt_getrandmax() + 1)), 1 / $value);
        if ($rand > $max) {
            $max = $rand;
            $result = $key;
        }
    }
    return $result;
}

// Находим команды у которых есть ШЮ
$res = $young->query('SELECT tt.*, ut.UserID
                        FROM (
                            SELECT MAX(b.Level) AS YCLevel, Alias, tb.TeamId
                            FROM OT_TeamBuildings tb, OT_Buildings b
                            WHERE b.BuildId = tb.BuildId 
                                AND b.Alias = "young_center"
                                AND tb.Status = 1 
                                AND tb.IsActive = 1
                            GROUP BY tb.TeamId
                        ) AS tt, OT_Team t, OT_UserTeam ut
                        WHERE t.TeamID = tt.TeamId
                            AND ut.TeamID = t.TeamID
                            AND t.Status IN (0, 1, 2)');

if ($res->num_rows != 0) {
    // Проверка от повторного запуска скрипта
    $checkStart = $young->query('SELECT DateUpdated 
                            FROM OT_SiteOptions 
                            WHERE OptionName = "young_progress_script" 
                                AND (DateUpdated < (NOW() - INTERVAL 23 HOUR) OR DateUpdated is NULL)');

    if ($checkStart->num_rows != 0) {
        while ($row = $res->fetch_assoc()) {
            // Находим всех юниоров из ШЮ
            $res_p = $young->query('SELECT p.*,
                                    r.on_exit tr_on_exit2, r.parry tr_parry2, r.pass tr_pass2, r.sel tr_sel2, 
                                    r.kick tr_kick2, r.dribl tr_dribl2, r.Corner tr_Corner2, r.Penalty tr_Penalty2, 
                                    r.Penalty11 tr_Penalty112
                                  FROM OT_Players p, OT_PlayerTeam pt
                                      LEFT JOIN OT_Reskills r ON (r.PlayerId = pt.PlayerID)
                                  WHERE p.Young = 1 
                                    AND pt.TeamID = ' . $row['TeamId'] . ' 
                                    AND pt.PlayerID = p.PlayerID
                                GROUP BY p.PlayerID');

            $staff = $young->VerifStaff($row['TeamId'], [14, 15, 16]);
            $talentVektor = NULL;
            // Общекомандное направление развития
            $res_pp = $young->query('SELECT *
                                    FROM OT_PreparePlayers
                                    WHERE TeamID = ' . $row['TeamId'] . '
                                        AND Status = 4
                                        AND TypePrepare IN ("' . implode('", "', array_values($talentsIco)) . '")');
            if ($res_pp->num_rows != 0) {
                $row_pp = $res_pp->fetch_assoc();
                $talentVektor = $row_pp['TypePrepare'];
            }

            if ($res_p->num_rows != 0) {
                while ($row_p = $res_p->fetch_assoc()) {
                    $row_3 = [];
                    if ($talentVektor !== NULL) {
                        $res_3 = $young->query('SELECT *
                                                FROM OT_PreparePlayers
                                                WHERE PlayerId = ' . $row_p['PlayerID'] . '
                                                    AND TeamId = ' . $row['TeamId'] . '
                                                    AND Status = 6
                                                    AND TypePrepare = "' . $talentVektor . '"');
                        if ($res_3->num_rows != 0) {
                            $row_3 = $res_3->fetch_assoc();
                        } else {
                            $row_3 = ['CountDayEffect' => 0];
                        }
                    }

                    $bonus = [];
                    $bonus['type'] = 'young';
                    // Находим по позиции какие навыки будут прогрессировать
                    $skillsPos = 0;
                    foreach ($positionBySkills as $key => $value) {
                        if (array_search($row_p['Position'], $value) !== false) {
                            $skillsPos = $key;
                            break;
                        }
                    }

                    // Находим по позиции какие таланты будут прогрессировать в случае прохода вероятности
                    $talentsPos = 0;
                    foreach ($positionByTalents as $key => $value) {
                        if (array_search($row_p['Position'], $value) !== false) {
                            $talentsPos = $key;
                            break;
                        }
                    }

                    // Заносим все прогрессирующие навыки игрока в один массив
                    $allSkills = [];
                    foreach ($skillsByGroup[$skillsPos] as $skills) {
                        foreach ($skills as $skill) {
                            $allSkills[$skill] = $row_p[$skill];
                        }
                    }

                    // Заносим все потенциально прогрессирующие таланты игрока в один массив
                    $allTalents = [];
                    foreach ($talentsByGroup[$talentsPos] as $talents) {
                        foreach ($talents as $talent) {
                            $allTalents[$talent] = $row_p[$talent];
                        }
                    }

                    // Если в таблице OT_Reskills переопределены группы навыков, то изменяем группы
                    $reskills = $skillsByGroup[$skillsPos];
                    foreach ($reskills as $key => $value) {
                        foreach ($value as $key_1 => $skill) {
                            if ($skill != 'tr_tactic' && $row_p[$skill . '2'] > 0 && $row_p[$skill . '2'] != $key) {
                                array_push($reskills[$row_p[$skill . '2']], $skill);
                                unset($reskills[$key][$key_1]);
                            }
                        }
                    }

                    // Если прогрессивность 60+, то выбираем случайный навык для усиленного прогресса
                    $randomSkill = -1;
                    if ($row_p['TalentProgress'] >= 60 && count($reskills[1]) > 0) {
                        $randomSkill = array_rand($reskills[1], 1);
                    }

                    // Заполняем прогресс навыков
                    foreach ($reskills as $key => $value) {
                        foreach ($value as $key_1 => $skill) {
                            // Если уровень школы недостаточен для прогресса данной группы навыков, то пропускаем
                            if (count($progressChance[$row['YCLevel']][$key]) == 0) {
                                continue;
                            }

                            // Рандомим прогресс навыка
                            $min = $progressChance[$row['YCLevel']][$key][0];
                            $max = $progressChance[$row['YCLevel']][$key][1];

                            if (in_array(14, $staff) && $key == 3) {
                                $min += 1;
                                $max += 1;
                            }

                            if (in_array(15, $staff) && $key == 2) {
                                $min += 2;
                                $max += 2;
                            }

                            if (in_array(16, $staff) && $skill == 'tr_tactic') {
                                $min += 5;
                                $max += 5;
                            }

                            if ($min <= 0 && $max <= 0) {
                                continue;
                            }

                            $progress = mt_rand($min, $max);
                            // Если сработала прогрессивность, то прогресс навыка +20%
                            if ($key == 1 && $randomSkill == $key_1) {
                                $progress = ceil($progress * 1.2);
                            }
                            // Бонус 6 уровня ШЮ (50% вероятность получить +40% от базового прогресса для тактики)
                            if ($row['YCLevel'] == 6 && mt_rand(0, 100) < 50 && $skill = 'tr_tactic') {
                                $progress = ceil($progress * 1.4);
                            }
                            // Получаем показатель навыка с учётом прогресса
                            $allSkills[$skill] = $allSkills[$skill] + $progress;
                            $bonus[$skill] = $progress;
                        }
                    }

                    // Шансы возникновения/невозникновения прогресса талантов по группам
                    $chanceGroup1 = [2 + $row['YCLevel'] * 0.6, 100 - (2 + $row['YCLevel'] * 0.6)];
                    $chanceGroup2 = [0.6 + $row['YCLevel'] * 0.3, 100 - (0.6 + $row['YCLevel'] * 0.3)];
                    $chanceGroup3 = [0.2 + $row['YCLevel'] * 0.1, 100 - (0.2 + $row['YCLevel'] * 0.1)];
                    $chanceTalent = $vektorUp = 0;

                    // Если прошла верятность, то добавляем к таланту 1 группы
                    foreach ($talentsByGroup[$talentsPos][1] as $value) {
                        // Если есть направление развития, прибавляем к шансу прогресса
                        if ($talentVektor !== NULL && !empty($row_3) && $talentsIco[$value] == $talentVektor) {
                            $percent = (3 + $row_3['CountDayEffect']) * 1.5;
                            $chanceTalent = customRandom([$chanceGroup1[0] + $percent, $chanceGroup1[1]]);
                        } else {
                            $chanceTalent = customRandom($chanceGroup1);  
                        }                        
                        if ($chanceTalent == 0) {
                            $vektorUp = $talentVektor == $talentsIco[$value] ? 1 : 0;
                            $allTalents[$value] = $allTalents[$value] + 1;
                            $bonus[$value] = 1;
                        }
                    }

                    // Если прошла верятность, то добавляем к таланту 2 группы
                    foreach ($talentsByGroup[$talentsPos][2] as $value) {
                        // Если есть направление развития, прибавляем к шансу прогресса
                        if ($talentVektor !== NULL && !empty($row_3) && $talentsIco[$value] == $talentVektor) {
                            $percent = (3 + $row_3['CountDayEffect']) * 1.5;
                            $chanceTalent = customRandom([$chanceGroup1[0] + $percent, $chanceGroup1[1]]);
                        } else {
                            $chanceTalent = customRandom($chanceGroup1);  
                        } 
                        if ($chanceTalent == 0) {
                            $vektorUp = $talentVektor == $talentsIco[$value] ? 1 : 0;
                            $allTalents[$value] = $allTalents[$value] + 1;
                            $bonus[$value] = 1;
                        }
                    }

                    // Если прошла верятность, то добавляем к таланту 3 группы
                    foreach ($talentsByGroup[$talentsPos][3] as $value) {
                        // Если есть направление развития, прибавляем к шансу прогресса
                        if ($talentVektor !== NULL && !empty($row_3) && $talentsIco[$value] == $talentVektor) {
                            $percent = (3 + $row_3['CountDayEffect']) * 1.5;
                            $chanceTalent = customRandom([$chanceGroup1[0] + $percent, $chanceGroup1[1]]);
                        } else {
                            $chanceTalent = customRandom($chanceGroup1);  
                        } 
                        if ($chanceTalent == 0) {
                            $vektorUp = $talentVektor == $talentsIco[$value] ? 1 : 0;
                            $allTalents[$value] = $allTalents[$value] + 1;
                            $bonus[$value] = 1;
                        }
                    }

                    if ($talentVektor !== NULL) {
                        $count = $vektorUp == 1 ? 0 : $row_3['CountDayEffect'] + 1;
                        if (isset($row_3['PlayerId'])) {
                            $young->query('UPDATE OT_PreparePlayers
                                            SET CountDayEffect = ' . $count . ', DateUpdated = NOW()
                                            WHERE PlayerId = ' . $row_p['PlayerID'] . '
                                                AND TeamId = ' . $row['TeamId'] . '
                                                AND Status = 6
                                                AND TypePrepare = "' . $talentVektor . '"');
                        } else {
                            $young->query('INSERT INTO OT_PreparePlayers (PlayerId, TeamId, Status, TypePrepare, CountDayEffect, DateAdded)
                                            VALUES (' . $row_p['PlayerID'] . ', ' . $row['TeamId'] . ', 6, "' . $talentVektor . '", ' . $count . ', NOW())');
                        }
                    }

                    // Составляем текст для sql-запроса
                    $query = [];
                    $query[] = 'YoungDay = YoungDay + 1';
                    foreach ($allSkills as $key => $value) {
                        if ($value >= 100) {
                            $value -= 100;
                            $query[] = $trToPar[$key] . ' = ' . $trToPar[$key] . ' + 1';
                        }
                        $query[] = $key . ' = ' . $value;
                    }

                    foreach ($allTalents as $key => $value) {
                        $temp = array_reverse(array_keys($allTalents));
                        if ($key == $temp[0]) {
                            $query[] = $key . ' = ' . $value;
                        } else {
                            $query[] = $key . ' = ' . $value;
                        }
                    }
                    // Обновляем показатели игрока
                    $young->query('UPDATE OT_Players SET ' . implode(', ', $query) . ' WHERE PlayerID = ' . $row_p['PlayerID']);
                    // Повышаем прайс за обучение
                    $young->query('UPDATE OT_PlayersYoung 
                                    SET Price = Price + ' . $upPrice[$row['YCLevel']] . '
                                    WHERE RealId = ' . $row_p['PlayerID']);
                    // Обновляем OT_PlayerStat
                    $bonus = addslashes(serialize($bonus));
                    $young->query('INSERT INTO OT_PlayerStat (PlayerId, TeamId, UpdateParam, Day) 
                                    VALUES (' . $row_p['PlayerID'] . ', ' . $row['TeamId'] . ', "' . $bonus . '", ' . $young->_options['day'] . ')');
                }
            }
        }
        // Ставим заметку о том, что скрипт прошёл
        $young->query('UPDATE OT_SiteOptions SET DateUpdated = NOW() WHERE OptionName = "young_progress_script"');
    }
}
