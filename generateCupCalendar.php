<?php
require_once '/var/www/oneteam/one-team.ru/kernel/public.php';

$calendar = new c_basis(9);

$season = $calendar->_options['day'] >= $calendar->_options['start_s' . ($calendar->_options['season'] + 1)] - 40 ? $calendar->_options['season'] + 1 : $calendar->_options['season'];

// Находим все проводимые кубки страны в сезоне, последнюю стадию с расписанием и максимальную стадию
$res = $calendar->query('SELECT oc.ChampID, oc.CountryID, MAX(gc.AreaId) AS AreaId, MAX(gc.StageId) AS StageId,
                            MAX(ca.AreaId) AS MaxArea
                        FROM OT_Championship oc
                            LEFT JOIN OT_GameChamp gc ON (gc.ChampId = oc.ChampID)  
                            LEFT JOIN OT_ChampArea ca ON (ca.ChampId = oc.ChampID), OT_Country c
                        WHERE oc.TypeChamp = 2 
                            AND oc.Season >= ' . $season . '
                            AND oc.CountryID != 253 
                            AND c.CountryID = oc.CountryID 
                            AND oc.IsNational=0
                        GROUP BY oc.ChampID
                        ORDER BY c.Zone ASC, oc.Name ASC');

// Проходимся по каждому и генерируем календарь на предстоящую стадию
if ($res->num_rows != 0) {
    /*
        Функция сортировки массива array по args, где args - массив с парой ключ-значение (ключ - по какому свойству
        производить сортировку, значение - asc или desc), приоритет сортировки зависит от порядка свойств в args.
    */
    function sortArr ($array, $args) {
        usort ($array, function($a, $b) use ($args) {
            $res = 0;
            foreach ($args as $k => $v) {
                if ($a[$k] == $b[$k]) {
                    continue;
                }
                $res = ($a[$k] < $b[$k]) ? -1 : 1;
                if ($v == 'desc') {
                    $res = -$res;
                }
                break;
            }
            return $res;
        });
        return $array;
    }

    while ($row = $res->fetch_assoc()) {
        // Если готово расписание для последнего этапа, то пропускаем
        if ($row['AreaId'] == $row['MaxArea']) {
            continue;
        }

        // Если сыграны не все игры текущего этапа, то пропускаем
        if (isset($row['AreaId'])) {
            $res_check = $calendar->query('SELECT SUM(IF(fg.StatusGame = 2, 1, 0)) AS Games, ca.NumTeams, ca.NumGames
                                        FROM OT_ChampArea ca
                                        LEFT JOIN OT_GameChamp gc ON (gc.ChampId = ca.ChampId AND gc.AreaId = ca.AreaId)
                                        LEFT JOIN OT_FutureGame fg ON (fg.GameId = gc.GameId)
                                        WHERE ca.ChampId = ' . $row['ChampID'] . '
                                            AND ca.AreaId = ' . $row['AreaId']);
            $row_check = $res_check->fetch_assoc();

            if ($row_check['Games'] < $row_check['NumTeams'] / 2 * $row_check['NumGames']) {
                continue;
            }
        }

        // Следующий по счёту этап кубка, который будем заполнять
        $areaId = isset($row['AreaId']) ? $row['AreaId'] + 1 : 1;
        // Следующая по счёту стадия кубка
        $stageId = isset($row['StageId']) ? $row['StageId'] + 1 : 1;

        // Находим количество команд и стадий для этапа, расписание для которого будем заполнять
        $res_num = $calendar->query('SELECT NumTeams, NumGames
                                        FROM OT_ChampArea
                                        WHERE ChampId = ' . $row['ChampID'] . '
                                            AND AreaId = ' . $areaId);
        if ($res_num->num_rows == 0) { // Редкий баг, значит некорректно заполнен кубок был
            continue;
        }
        $row_num = $res_num->fetch_assoc();

        // Находим команды, которые будут участвовать в этапе
        if ($areaId == 1) {
            /*
                Если есть данные о прошлом сезоне, то выбираем занявших последние места сортируя по приоритету
                чемпионата
            */
            $res_1 = $calendar->query('SELECT t.TeamID, t.Power, ch.ChampPriority, 1 AS NewTeam, 0 AS HomeGames, 
                                        MAX(md.Points) AS Points
                                FROM OT_Team t, OT_TeamChamp tc, OT_Championship ch, OT_MemberDetails md
                                WHERE IF(t.FederationID is NULL, t.CountryID, t.FederationID) = ' . $row['CountryID'] . '
                                    AND tc.TeamID = t.TeamID
                                    AND tc.Status = 1
                                    AND ch.ChampID = tc.ChampID 
                                    AND ch.Season = ' . ($season - 1) . '
                                    AND ch.TypeChamp = 1
                                    AND md.ChampId = tc.ChampID
                                    AND md.TeamId = tc.TeamID
                                GROUP BY t.TeamID
                                ORDER BY ch.ChampPriority DESC, Points
                                LIMIT ' . $row_num['NumTeams']);

            // Если нет данных о прошлом сезоне, то выбираем по приоритету чемпионата и мощи
            if ($res_1->num_rows == 0) {
                $res_1 = $calendar->query('SELECT t.TeamID, t.Power, ch.ChampPriority, 1 AS NewTeam, 0 AS HomeGames
                                FROM OT_Team t, OT_TeamChamp tc, OT_Championship ch
                                WHERE IF(t.FederationID is NULL, t.CountryID, t.FederationID) = ' . $row['CountryID'] . '
                                    AND tc.TeamID = t.TeamID
                                    AND tc.Status = 1
                                    AND ch.ChampID = tc.ChampID 
                                    AND ch.Season = ' . $season . '
                                    AND ch.TypeChamp = 1
                                GROUP BY t.TeamID
                                ORDER BY ch.ChampPriority DESC, t.Power
                                LIMIT ' . $row_num['NumTeams']);
            }
        } else {
            // Выбираем всех победителей прошлой стадии и добавляем команды, если их не хватает
            $res_1 = $calendar->query('(SELECT t.TeamID, t.Power, ch.ChampPriority, 0 AS NewTeam,
            (SELECT SUM(IF(fg.TeamIdHome = t.TeamID, 1, 0)) FROM OT_FutureGame fg JOIN OT_GameChamp gc ON (fg.GameId = gc.GameId AND ChampId = ' . $row['ChampID'] . ' AND AreaId < ' . $areaId . ')) AS HomeGames, 0 AS Points
                                FROM OT_Team t, OT_TeamChamp tc, OT_Championship ch
                                WHERE IF(t.FederationID is NULL, t.CountryID, t.FederationID) = ' . $row['CountryID'] . '
                                    AND tc.TeamID = t.TeamID
                                    AND tc.Status = 1
                                    AND ch.ChampID = tc.ChampID 
                                    AND ch.Season = ' . $season . '
                                    AND ch.TypeChamp = 1
                                    AND t.TeamID IN (SELECT TeamID 
                                                        FROM OT_TeamChamp 
                                                        WHERE ChampID = ' . $row['ChampID'] . '
                                                            AND Stage = ' . ($areaId - 1) . '
                                                            AND Status = 1
                                                            AND Result = 1)
                                GROUP BY t.TeamID)
                            UNION
                                (SELECT t.TeamID, t.Power, ch.ChampPriority, 1 AS NewTeam, 0 AS HomeGames,
                                    MAX(md.Points) AS Points
                                FROM OT_Team t, OT_TeamChamp tc, OT_Championship ch, OT_MemberDetails md
                                WHERE IF(t.FederationID is NULL, t.CountryID, t.FederationID) = ' . $row['CountryID'] . '
                                    AND tc.TeamID = t.TeamID
                                    AND tc.Status = 1
                                    AND ch.ChampID = tc.ChampID 
                                    AND ch.Season = ' . ($season - 1) . '
                                    AND ch.TypeChamp = 1
                                    AND md.ChampId = tc.ChampID
                                    AND md.TeamId = tc.TeamID
                                    AND t.TeamID NOT IN (SELECT TeamID 
                                                        FROM OT_TeamChamp 
                                                        WHERE ChampID = ' . $row['ChampID'] . '
                                                            AND Stage < ' . $areaId  . ')
                                GROUP BY t.TeamID)
                            ORDER BY NewTeam, ChampPriority DESC, Points
                            LIMIT ' . $row_num['NumTeams']);
            // Если не было чемпионата прошлого сезона, докидываем команды по мощи
            if ($res_1->num_rows < $row_num['NumTeams']) {
                $res_1 = $calendar->query('(SELECT t.TeamID, t.Power, ch.ChampPriority, 0 AS NewTeam,
                (SELECT SUM(IF(fg.TeamIdHome = t.TeamID, 1, 0)) FROM OT_FutureGame fg JOIN OT_GameChamp gc ON (fg.GameId = gc.GameId AND ChampId = ' . $row['ChampID'] . ' AND AreaId < ' . $areaId . ')) AS HomeGames
                                    FROM OT_Team t, OT_TeamChamp tc, OT_Championship ch
                                    WHERE IF(t.FederationID is NULL, t.CountryID, t.FederationID) = ' . $row['CountryID'] . '
                                        AND tc.TeamID = t.TeamID
                                        AND tc.Status = 1
                                        AND ch.ChampID = tc.ChampID 
                                        AND ch.Season = ' . $season . '
                                        AND ch.TypeChamp = 1
                                        AND t.TeamID IN (SELECT TeamID 
                                                            FROM OT_TeamChamp 
                                                            WHERE ChampID = ' . $row['ChampID'] . '
                                                                AND Stage = ' . ($areaId - 1) . '
                                                                AND Status = 1
                                                                AND Result = 1)
                                    GROUP BY t.TeamID)
                                UNION
                                    (SELECT t.TeamID, t.Power, ch.ChampPriority, 1 AS NewTeam, 0 AS HomeGames
                                    FROM OT_Team t, OT_TeamChamp tc, OT_Championship ch
                                    WHERE IF(t.FederationID is NULL, t.CountryID, t.FederationID) = ' . $row['CountryID'] . '
                                        AND tc.TeamID = t.TeamID
                                        AND tc.Status = 1
                                        AND ch.ChampID = tc.ChampID 
                                        AND ch.Season = ' . $season . '
                                        AND ch.TypeChamp = 1
                                        AND t.TeamID NOT IN (SELECT TeamID 
                                                            FROM OT_TeamChamp 
                                                            WHERE ChampID = ' . $row['ChampID'] . '
                                                                AND Stage < ' . $areaId  . ')
                                    GROUP BY t.TeamID)
                                ORDER BY NewTeam, ChampPriority DESC, Power
                                LIMIT ' . $row_num['NumTeams']);
            }
        }

        // Заносим информацию о командах в массив и в таблицу OT_TeamChamp
        $teamsInfo = [];
        while ($row_1 = $res_1->fetch_assoc()) {
            $teamsInfo[] = $row_1;
            $calendar->query('INSERT INTO OT_TeamChamp (ChampId, Stage, Status, TeamId) 
                                VALUES (' . $row['ChampID'] . ', ' . $areaId . ', 1, ' . $row_1['TeamID'] . ')');
        }

        // Пытаемся найти готовое расписание
        $resSh = $calendar->query('SELECT Days 
                                    FROM OT_PredefinedSheduleFed
                                    WHERE Status = 1 
                                    AND ChampId = ' . $row['ChampID']);

        $cupDays = [];
        // Если есть уже составленное расписание, берём его, иначе генерируем
        if ($resSh->num_rows != 0) {
            $rowSh = $resSh->fetch_assoc();
            $cupDays = unserialize($rowSh['Days']);
        } else {
            // Находим день начала сезона, день финала кубка и номера этих дней в неделе
            $res_2 = $calendar->query('SELECT Day, WEEKDAY(RealDate) AS NumDay
                                        FROM OT_Shedule
                                        WHERE Season = ' . $season . '
                                            AND (Descr_2 LIKE "%[SS]%" OR Descr_2 LIKE "%[CUP_FIN]%")
                                        ORDER BY Day');
            $thursdays = $days = [];
            if ($res_2->num_rows != 0) {
                $row_2 = $res_2->fetch_assoc();
                $days['SS'] = $row_2;
                $row_2 = $res_2->fetch_assoc();
                $days['CUP_FIN'] = $row_2;
            }

            // Находим количество дней, которые необходимо задействовать, за исключением финала
            $resNG = $calendar->query('SELECT SUM(NumGames) AS CountDays
                                        FROM OT_ChampArea 
                                        WHERE ChampId = ' . $row['ChampID'] . '
                                            AND Name != "Финал"');

            $rowNG = $resNG->fetch_assoc();
            $countDays = $rowNG['CountDays'];

            // Находим первый четверг сезона
            for (
                $day = $days['SS']['Day'], $numDay = $days['SS']['NumDay'];
                $day < $days['CUP_FIN']['Day'];
                $day += 2, $numDay++
            ) {
                // Если нашли четверг, останавливаем поиск, если дошли до воскресенья, скидываем счётчик на понедельник
                if ($numDay == 3) {
                    $thursdays[] = $day;
                    break;
                } elseif ($numDay == 6) {
                    $numDay = 0;
                }
            }

            // Находим все четверги сезона
            for ($day = $thursdays[0]; $day < $days['CUP_FIN']['Day']; $day += 14) {
                $thursdays[] = $day;
            }

            // Назначаем первую стадию на первый четверг
            $cupDays[] = $thursdays[0];
            $nextDay = ceil(count($thursdays) / $countDays);
            $counter = count($thursdays) - $nextDay - 1;

            // Далее распределяем все стадии равномерно
            for ($i = $countDays - 1; $i > 0; $i--) {
                $cupDays[] = $thursdays[$nextDay];
                $nextDay += ceil($counter / $i);
                $counter = count($thursdays) - $nextDay - 1;
            }

            // Добавляем день финала в расписание
            $cupDays[] = $days['CUP_FIN']['Day'];

            /*
                Добавляем полученное расписание в OT_PredefinedSheduleFed, при заполнении следующих стадий, будем
                обращаться к нему
            */
            $calendar->query('INSERT INTO OT_PredefinedSheduleFed (ChampID, Status, DateUpdated, Days, AutoGen)
                                VALUES (' . $row['ChampID'] . ', 1, NOW(), ' . serialize($cupDays) . ', 1)');
        }

        $cart1 = $cart2 = [];
        shuffle($teamsInfo);
        // Если это финал, то выбираем хозяев в зависимости от стадиона
        if (count($teamsInfo) == 2) {
            $stad1 = $calendar->query('SELECT MAX(b.Level) AS SLevel
                                        FROM OT_Buildings b, OT_TeamBuildings tb 
                                        WHERE b.ParentId = 8 
                                            AND tb.BuildId = b.BuildId 
                                            AND tb.TeamId = ' . $teamsInfo[0]['TeamID'])->fetch_assoc()['SLevel'];
            $stad2 = $calendar->query('SELECT MAX(b.Level) AS SLevel
                                        FROM OT_Buildings b, OT_TeamBuildings tb 
                                        WHERE b.ParentId = 8 
                                            AND tb.BuildId = b.BuildId 
                                            AND tb.TeamId = ' . $teamsInfo[1]['TeamID'])->fetch_assoc()['SLevel'];

            // Если стадионы одинаковы, то хозяев выбираем случайно, иначе хозяин тот, у кого больше стадион
            if ($stad1 == $stad2) {
                $rand = mt_rand(1, 2);
                $cart1[0] = $rand == 1 ? $teamsInfo[0] : $teamsInfo[1];
                $cart2[0] = $rand == 1 ? $teamsInfo[1] : $teamsInfo[0];
            } else {
                $cart1[0] = $stad1 > $stad2 ? $teamsInfo[0] : $teamsInfo[1];
                $cart2[0] = $stad1 > $stad2 ? $teamsInfo[1] : $teamsInfo[0];
            }

        } else {
            // Сортируем команды в зависимости от стадии
            if ($areaId < ($row['MaxArea'] - 2)) {
                if ($areaId == 2) {
                    $teamsInfo = sortArr($teamsInfo, ['NewTeam' => 'asc', 'ChampPriority' => 'asc', 'Power' => 'asc']);
                } elseif ($areaId == 1) {
                    $teamsInfo = sortArr($teamsInfo, ['ChampPriority' => 'asc', 'Power' => 'asc']);
                } else {
                    $teamsInfo = sortArr($teamsInfo, ['ChampPriority' => 'asc']);
                }

            } elseif ($areaId == 1 || $areaId == 2) {
                $teamsInfo = sortArr($teamsInfo, ['Power' => 'asc']);
            }

            // Делим отсортированные команды на две корзины
            $cart1 = array_slice($teamsInfo, 0, count($teamsInfo) / 2);
            $cart2 = array_slice($teamsInfo, count($teamsInfo) / 2, count($teamsInfo) / 2);
            // Мешаем корзины
            shuffle($cart1);
            shuffle($cart2);
        }

        // Составляем пары
        $pairs = [];
        for ($i = 0; $i < count($cart1); $i++) {
            $h = $a = [];
            /*
                В зависимости от количества уже сыгранных домашних матчей в этом кубке выбираем кто будет играть
                дома, а если это финал, то дома будет играть тот, у кого больше стадион
            */
            if (count($cart1) == 1) {
                $h = $cart1[$i];
                $a = $cart2[$i];
            } elseif (
                $cart1[$i]['NewTeam'] == 1 && $cart2[$i]['NewTeam'] == 1
                || $cart1[$i]['HomeGames'] == $cart2[$i]['HomeGames']
            ) {
                $rand = mt_rand(1, 2);
                $h = $rand == 1 ? $cart1[$i] : $cart2[$i];
                $a = $rand == 1 ? $cart2[$i] : $cart1[$i];
            } else {
                $h = $cart1[$i]['HomeGames'] > $cart2[$i]['HomeGames'] ? $cart2[$i] : $cart1[$i];
                $a = $cart1[$i]['HomeGames'] > $cart2[$i]['HomeGames'] ? $cart1[$i] : $cart2[$i];
            }
            $pairs[] = ['h' => $h['TeamID'], 'a' => $a['TeamID']];
        }

        // Находим номер дня из расписания, на который нужно назначить встречу
        $matchDay = 0;
        foreach ($cupDays as $key => $value) {
            if ($value > $calendar->_options['day']) {
                $matchDay = $key;
                break;
            }
        }

        $typeGame = $row_check['NumGames'] == 1 ? 2 : 3;
        $codeGame = $row_check['NumGames'] == 1 ? 3 : 1;
        // Заносим встречи в календарь, если несколько стадий в этапе, то каждый раз меняем местами хозяев и гостей
        for ($i = 0; $i < $row_num['NumGames']; $i++) {
            foreach ($pairs as $key => $value) {
                $calendar->query('INSERT INTO OT_FutureGame (TeamIdOne, TeamIdTwo, TeamIdHome, GameDaySite, TypeGame, CodeGame)
                            VALUES ('
                            . $value['h'] . ', '
                            . $value['a'] . ', '
                            . $value['h'] . ', '
                            . $cupDays[$matchDay] . ', ' . $typeGame . ', ' . $codeGame . ')');
                $gameId = $calendar->insert_id;
                $calendar->query('INSERT INTO OT_GameChamp (GameId, ChampId, StageId, AreaId)
                                    VALUES ('
                                    . $gameId . ', '
                                    . $row['ChampID'] . ', '
                                    . $stageId . ', '
                                    . $areaId . ')');
                $calendar->query('INSERT INTO OT_GameParameters (GameId) VALUES (' . $gameId . ')');
                /*
                    Если больше одной встречи в этапе, то меняем местами хозяев и гостей, чтобы на следующей
                    итерации поменять местами команды
                */
                $swap = $value['h'];
                $pairs[$key]['h'] = $pairs[$key]['a'];
                $pairs[$key]['a'] = $swap;
            }
            // Если будет ещё одна итерация, то матчи назначаем на следующий день и это будет следующей стадией
            $matchDay++;
            $stageId++;
        }
    }
}
