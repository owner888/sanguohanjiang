# 旧版接口缺口清单

对比基线：前端 `public/static/js` 中写死的 `c`/`m` 调用 vs 新版 `src/Handler` 的 `public static` 方法 vs 旧版 CodeIgniter `wwwroot/s1`、`wwwroot/uc` 控制器。动态拼接的调用未覆盖。

- 前端调用：653；已实现：309；缺失：344（旧版有 322，旧版也无 22）
- 「旧版位置」为相对旧服务端 `HJServer/wwwroot/` 的路径；「—」表示旧版 s1/uc 中没有，需按前端字段反推（部分可能在 `battle`/`war` 服）。
- 标记 🆕 的控制器在新版中整个 Handler 都不存在。

## 缺失接口（按控制器，缺口多的在前）

### god 🆕（14）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=god&m=entry` | `s1/application/controllers/god.php:238` | `static/js/view/championsworld.js:385` |
| `c=god&m=get_report_info` | `s1/application/controllers/god.php:771` | `static/js/view/championsworld.js:538`<br>`static/js/view/miscpop.js:3139` |
| `c=god&m=get_report_list` | `s1/application/controllers/god.php:405` | `static/js/view/championsworld.js:579` |
| `c=god&m=get_reward` | `s1/application/controllers/god.php:645` | `static/js/view/championsworld.js:754` |
| `c=god&m=get_reward_list` | `s1/application/controllers/god.php:472` | `static/js/view/championsworld.js:705` |
| `c=god&m=get_reward_pre` | `s1/application/controllers/god.php:968` | `static/js/view/championsrewardpop.js:48` |
| `c=god&m=get_support` | `s1/application/controllers/god.php:367` | `static/js/view/championsworld.js:907` |
| `c=god&m=index` | `s1/application/controllers/god.php:32` | `static/js/view/championsworld.js:128`<br>`static/js/view/championsworld.js:82` |
| `c=god&m=loading` | `s1/application/controllers/god.php:1088` | `static/js/view/loginworld.js:733` |
| `c=god&m=palace` | `s1/application/controllers/god.php:892` | `static/js/view/palaceheroworld.js:36` |
| `c=god&m=palace_report` | `s1/application/controllers/god.php:926` | `static/js/view/palaceheroworld.js:267` |
| `c=god&m=share_report` | `s1/application/controllers/god.php:818` | `static/js/view/miscpop.js:5515` |
| `c=god&m=support` | `s1/application/controllers/god.php:341` | `static/js/view/championsworld.js:1103` |
| `c=god&m=sync_battle_info` | `s1/application/controllers/god.php:297` | `static/js/view/championsworld.js:1051` |

### tower（12）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=tower&m=buy` | `s1/application/controllers/tower.php:1095` | `static/js/view/towerworld.js:684` |
| `c=tower&m=get_mission_info` | `s1/application/controllers/tower.php:311` | `static/js/view/towerworld.js:559` |
| `c=tower&m=get_mission_list` | `s1/application/controllers/tower.php:146` | `static/js/view/towerworld.js:331`<br>`static/js/view/towerworld.js:502` |
| `c=tower&m=get_mopup_price` | `s1/application/controllers/tower.php:453` | `static/js/view/towerworld.js:168` |
| `c=tower&m=get_price` | `s1/application/controllers/tower.php:1070` | `static/js/view/towerworld.js:657` |
| `c=tower&m=get_report_info` | `s1/application/controllers/tower.php:1031` | `static/js/view/miscpop.js:2563` |
| `c=tower&m=get_report_list` | `s1/application/controllers/tower.php:1007` | `static/js/view/towerworld.js:630` |
| `c=tower&m=get_reward` | `s1/application/controllers/tower.php:1268` | `static/js/view/miscpop.js:2696` |
| `c=tower&m=get_scene_list` | `s1/application/controllers/tower.php:84` | `static/js/view/towerworld.js:45`<br>`static/js/view/towerworld.js:66` |
| `c=tower&m=mop_up` | `s1/application/controllers/tower.php:537` | `static/js/view/towerworld.js:244` |
| `c=tower&m=pk` | `s1/application/controllers/tower.php:768` | `static/js/view/towerworld.js:718` |
| `c=tower&m=reward_info` | `s1/application/controllers/tower.php:1163` | `static/js/view/miscpop.js:2708` |

### beauty（11）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=beauty&m=advance_action` | `s1/application/controllers/beauty.php:2673` | `static/js/view/beautypop.js:1873` |
| `c=beauty&m=advance_index` | `s1/application/controllers/beauty.php:2534` | `static/js/view/beautypop.js:1759` |
| `c=beauty&m=buy_loot_times` | `s1/application/controllers/beauty.php:2174` | `static/js/view/beautypop.js:2254` |
| `c=beauty&m=cancel_levelup` | `s1/application/controllers/beauty.php:3122` | `static/js/view/beautypop.js:1733` |
| `c=beauty&m=conversion_chip` | `s1/application/controllers/beauty.php:4165` | `static/js/view/beautypop.js:1934` |
| `c=beauty&m=get_say_info` | `s1/application/controllers/beauty.php:761` | `static/js/view/beautypop.js:1900` |
| `c=beauty&m=level_up` | `s1/application/controllers/beauty.php:2876` | `static/js/view/beautypop.js:1508` |
| `c=beauty&m=level_up_status` | `s1/application/controllers/beauty.php:3087` | `static/js/view/beautypop.js:909` |
| `c=beauty&m=loot_action` | `s1/application/controllers/beauty.php:1671` | `static/js/view/beautypop.js:2307` |
| `c=beauty&m=loot_index` | `s1/application/controllers/beauty.php:1269` | `static/js/view/beautypop.js:1989` |
| `c=beauty&m=loot_reflush` | `s1/application/controllers/beauty.php:1564` | `static/js/view/beautypop.js:2209` |

### soul（11）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=soul&m=convert` | `s1/application/controllers/soul.php:554` | `static/js/view/soulworld.js:770`<br>`static/js/view/soulworld.js:777` |
| `c=soul&m=equip` | `s1/application/controllers/soul.php:667` | `static/js/view/soulworld.js:830` |
| `c=soul&m=get_general_info` | `s1/application/controllers/soul.php:316` | `static/js/view/soulworld.js:915` |
| `c=soul&m=get_pack_info` | `s1/application/controllers/soul.php:927` | `static/js/view/soulworld.js:1234` |
| `c=soul&m=get_soul_info` | `s1/application/controllers/soul.php:224` | `static/js/view/soulworld.js:1004` |
| `c=soul&m=get_unlock_price` | `s1/application/controllers/soul.php:804` | `static/js/view/soulworld.js:1108` |
| `c=soul&m=levelup` | `s1/application/controllers/soul.php:455` | `static/js/view/soulworld.js:1068` |
| `c=soul&m=merge_action` | `s1/application/controllers/soul.php:1012` | `static/js/view/soulworld.js:1718` |
| `c=soul&m=merge_index` | `s1/application/controllers/soul.php:964` | `static/js/view/soulworld.js:1686` |
| `c=soul&m=unequip` | `s1/application/controllers/soul.php:746` | `static/js/view/soulworld.js:870` |
| `c=soul&m=unlock` | `s1/application/controllers/soul.php:852` | `static/js/view/soulworld.js:1160` |

### act_treasure 🆕（10）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_treasure&m=hanzi_achieve` | `s1/application/controllers/act_treasure.php:1803` | `static/js/view/acttreasurepop.js:1472` |
| `c=act_treasure&m=hanzi_achieve_reward` | `s1/application/controllers/act_treasure.php:1975` | `static/js/view/acttreasurepop.js:1605` |
| `c=act_treasure&m=hanzi_exchange` | `s1/application/controllers/act_treasure.php:1232` | `static/js/view/acttreasurepop.js:1108` |
| `c=act_treasure&m=hanzi_index` | `s1/application/controllers/act_treasure.php:1185` | `static/js/view/acttreasurepop.js:990` |
| `c=act_treasure&m=index` | `s1/application/controllers/act_treasure.php:78` | `static/js/view/acttreasurepop.js:67` |
| `c=act_treasure&m=today_reward` | `s1/application/controllers/act_treasure.php:787` | `static/js/view/acttreasurepop.js:1177` |
| `c=act_treasure&m=treasure` | `s1/application/controllers/act_treasure.php:189` | `static/js/view/acttreasurepop.js:546` |
| `c=act_treasure&m=treasure_rank` | `s1/application/controllers/act_treasure.php:1422` | `static/js/view/acttreasurepop.js:1260` |
| `c=act_treasure&m=treasure_rank_reward` | `s1/application/controllers/act_treasure.php:1622` | `static/js/view/acttreasurepop.js:1380` |
| `c=act_treasure&m=treasure_two` | `s1/application/controllers/act_treasure.php:538` | `static/js/view/acttreasurepop.js:1701` |

### war（10）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=war&m=get_palace_list` | `s1/application/controllers/war.php:238` | `static/js/view/warheroworld.js:289`<br>`static/js/view/warheroworld.js:64` |
| `c=war&m=get_rank_list` | `s1/application/controllers/war.php:133` | `static/js/view/warheroworld.js:353` |
| `c=war&m=get_report_info` | `s1/application/controllers/war.php:735` | `static/js/view/warheroworld.js:461`<br>`static/js/view/warworld.js:657` |
| `c=war&m=get_report_list` | `s1/application/controllers/war.php:395` | `static/js/view/warheroworld.js:394`<br>`static/js/view/warworld.js:698` |
| `c=war&m=get_reward` | `s1/application/controllers/war.php:618` | `static/js/view/warworld.js:862` |
| `c=war&m=get_reward_list` | `s1/application/controllers/war.php:469` | `static/js/view/warworld.js:813` |
| `c=war&m=get_support` | `s1/application/controllers/war.php:356` | `static/js/view/warworld.js:1036` |
| `c=war&m=share_report` | `s1/application/controllers/war.php:803` | `static/js/view/miscpop.js:5624` |
| `c=war&m=support` | `s1/application/controllers/war.php:328` | `static/js/view/warworld.js:1231` |
| `c=war&m=sync_battle_info` | `s1/application/controllers/war.php:282` | `static/js/view/warworld.js:1179` |

### worldarena（9）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=worldarena&m=arena` | `s1/application/controllers/worldarena.php:710` | `static/js/view/worldarenapop.js:221` |
| `c=worldarena&m=betting` | `s1/application/controllers/worldarena.php:990` | `static/js/view/worldarenapop.js:840` |
| `c=worldarena&m=buy_times` | `s1/application/controllers/worldarena.php:1084` | `static/js/view/worldarenapop.js:800` |
| `c=worldarena&m=exchange_item` | `s1/application/controllers/worldarena.php:1562` | `static/js/view/worldarenapop.js:887` |
| `c=worldarena&m=get_rank_list` | `s1/application/controllers/worldarena.php:787` | `static/js/view/worldarenapop.js:326` |
| `c=worldarena&m=get_report_info` | `s1/application/controllers/worldarena.php:1461` | `static/js/view/miscpop.js:2447`<br>`static/js/view/miscpop.js:3125`<br>`static/js/view/worldarenapop.js:760` |
| `c=worldarena&m=get_report_list` | `s1/application/controllers/worldarena.php:1411` | `static/js/view/worldarenapop.js:390` |
| `c=worldarena&m=get_server_reward` | `s1/application/controllers/worldarena.php:1734` | `static/js/core/player.js:799` |
| `c=worldarena&m=pk` | `s1/application/controllers/worldarena.php:1151` | `static/js/view/worldarenapop.js:726` |

### act_moon 🆕（8）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_moon&m=buy_moon` | `s1/application/controllers/act_moon.php:950` | `static/js/view/midautumnpop.js:211` |
| `c=act_moon&m=eat_moon` | `s1/application/controllers/act_moon.php:542` | `static/js/view/midautumnpop.js:548`<br>`static/js/view/midautumnpop.js:589` |
| `c=act_moon&m=get_ach_list` | `s1/application/controllers/act_moon.php:902` | `static/js/view/midautumnpop.js:300` |
| `c=act_moon&m=get_friend_list` | `s1/application/controllers/act_moon.php:174` | `static/js/view/midautumnpop.js:438` |
| `c=act_moon&m=get_give_info` | `s1/application/controllers/act_moon.php:491` | `static/js/view/midautumnpop.js:362` |
| `c=act_moon&m=get_reward` | `s1/application/controllers/act_moon.php:636` | `static/js/view/midautumnpop.js:836` |
| `c=act_moon&m=give_moon` | `s1/application/controllers/act_moon.php:238` | `static/js/view/midautumnpop.js:659` |
| `c=act_moon&m=index` | `s1/application/controllers/act_moon.php:91` | `static/js/view/midautumnpop.js:48` |

### newyearboss 🆕（8）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=newyearboss&m=battle` | `s1/application/controllers/newyearboss.php:177` | `static/js/view/miscpop.js:1997` |
| `c=newyearboss&m=get_achievement_list` | `s1/application/controllers/newyearboss.php:617` | `static/js/view/nianworld.js:352` |
| `c=newyearboss&m=get_achievement_reward` | `s1/application/controllers/newyearboss.php:740` | `static/js/view/nianworld.js:442` |
| `c=newyearboss&m=get_rank` | `s1/application/controllers/newyearboss.php:401` | `static/js/view/nianworld.js:598` |
| `c=newyearboss&m=get_rank_reward` | `s1/application/controllers/newyearboss.php:467` | `static/js/view/nianworld.js:675` |
| `c=newyearboss&m=get_rank_reward_list` | `s1/application/controllers/newyearboss.php:699` | `static/js/view/nianworld.js:797` |
| `c=newyearboss&m=index` | `s1/application/controllers/newyearboss.php:48` | `static/js/view/nianworld.js:52` |
| `c=newyearboss&m=powerup` | `s1/application/controllers/newyearboss.php:313` | `static/js/view/nianworld.js:719` |

### christmasbuy 🆕（7）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=christmasbuy&m=add_good` | `s1/application/controllers/christmasbuy.php:225` | `static/js/view/christmasbuypop.js:444` |
| `c=christmasbuy&m=buy_good` | `s1/application/controllers/christmasbuy.php:442` | `static/js/view/christmasbuypop.js:372` |
| `c=christmasbuy&m=del_car_good` | `s1/application/controllers/christmasbuy.php:331` | `static/js/view/christmasbuypop.js:405` |
| `c=christmasbuy&m=index` | `s1/application/controllers/christmasbuy.php:48` | `static/js/view/christmasbuypop.js:53` |
| `c=christmasbuy&m=play_introduce` | `s1/application/controllers/christmasbuy.php:883` | `static/js/view/christmasbuypop.js:741` |
| `c=christmasbuy&m=rebate_reward` | `s1/application/controllers/christmasbuy.php:759` | `static/js/view/christmasbuypop.js:604` |
| `c=christmasbuy&m=rebate_reward_index` | `s1/application/controllers/christmasbuy.php:681` | `static/js/view/christmasbuypop.js:547` |

### copies（7）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=copies&m=buy_times` | — | `static/js/view/pacifypop.js:486` |
| `c=copies&m=get_monster_info` | — | `static/js/view/pacifypop.js:330` |
| `c=copies&m=get_monster_list` | — | `static/js/view/pacifypop.js:141` |
| `c=copies&m=get_mopup_price` | — | `static/js/view/pacifypop.js:636` |
| `c=copies&m=get_report_info` | — | `static/js/view/miscpop.js:5750` |
| `c=copies&m=get_report_list` | — | `static/js/view/pacifypop.js:869` |
| `c=copies&m=send_to_chat` | — | `static/js/view/miscpop.js:5995` |

### expostulation（7）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=expostulation&m=authstr` | `s1/application/controllers/expostulation.php:341` | `static/js/view/expostulationpop.js:533` |
| `c=expostulation&m=expostulation_list` | `s1/application/controllers/expostulation.php:502` | `static/js/view/expostulationpop.js:568` |
| `c=expostulation&m=get_expostulation_by_user` | `s1/application/controllers/expostulation.php:23` | `static/js/view/countryworld.js:132` |
| `c=expostulation&m=get_reward` | `s1/application/controllers/expostulation.php:235` | `static/js/view/expostulationpop.js:435` |
| `c=expostulation&m=put_expostulation` | `s1/application/controllers/expostulation.php:557` | `static/js/view/expostulationpop.js:655` |
| `c=expostulation&m=refresh` | `s1/application/controllers/expostulation.php:745` | `static/js/view/expostulationpop.js:606` |
| `c=expostulation&m=support` | `s1/application/controllers/expostulation.php:665` | `static/js/view/expostulationpop.js:374` |

### gem（7）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=gem&m=gem_turn` | `s1/application/controllers/gem.php:552` | `static/js/view/gemworld.js:1610`<br>`static/js/view/gemworld.js:1617`<br>`static/js/view/gemworld.js:637`<br>…共 4 处 |
| `c=gem&m=get_20gem_by_page` | `s1/application/controllers/gem.php:822` | `static/js/view/gemworld.js:445` |
| `c=gem&m=get_gem_by_id` | `s1/application/controllers/gem.php:183` | `static/js/view/gemworld.js:1768` |
| `c=gem&m=get_gem_by_page` | `s1/application/controllers/gem.php:723` | `static/js/view/gemworld.js:1980` |
| `c=gem&m=get_gem_info` | `s1/application/controllers/gem.php:680` | `static/js/view/gemworld.js:1855`<br>`static/js/view/gemworld.js:476` |
| `c=gem&m=level_up` | `s1/application/controllers/gem.php:452` | `static/js/view/gemworld.js:1930`<br>`static/js/view/gemworld.js:550` |
| `c=gem&m=mergegem_index` | `s1/application/controllers/gem.php:752` | `static/js/view/gemworld.js:277` |

### heaven（7）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=heaven&m=buy` | `s1/application/controllers/heaven.php:1005` | `static/js/view/heavenworld.js:504` |
| `c=heaven&m=get_mopup_price` | `s1/application/controllers/heaven.php:233` | `static/js/view/heavenworld.js:555` |
| `c=heaven&m=get_price` | `s1/application/controllers/heaven.php:1441` | `static/js/view/heavenworld.js:464` |
| `c=heaven&m=get_report_info` | `s1/application/controllers/heaven.php:965` | `static/js/view/miscpop.js:2600` |
| `c=heaven&m=get_report_list` | `s1/application/controllers/heaven.php:940` | `static/js/view/heavenworld.js:362` |
| `c=heaven&m=get_reward` | `s1/application/controllers/heaven.php:1215` | `static/js/view/miscpop.js:2906` |
| `c=heaven&m=reward_info` | `s1/application/controllers/heaven.php:1065` | `static/js/view/miscpop.js:2918` |

### information（7）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=information&m=get_all_beauty_info` | `s1/application/controllers/information.php:1112` | `static/js/view/infopop.js:378` |
| `c=information&m=get_country_info` | `s1/application/controllers/information.php:984` | `static/js/view/infopop.js:675` |
| `c=information&m=get_general_gem` | `s1/application/controllers/information.php:734` | `static/js/view/infopop.js:1162` |
| `c=information&m=get_general_mount` | `s1/application/controllers/information.php:825` | `static/js/view/infopop.js:1289` |
| `c=information&m=get_own_soul` | `s1/application/controllers/information.php:464` | `static/js/view/infopop.js:806` |
| `c=information&m=get_star_info` | `s1/application/controllers/information.php:1059` | `static/js/view/infopop.js:491` |
| `c=information&m=get_talent_list` | `s1/application/controllers/information.php:710` | `static/js/view/infopop.js:895` |

### worldcup 🆕（7）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=worldcup&m=action` | `s1/application/controllers/worldcup.php:282` | `static/js/view/worldcuppop.js:708` |
| `c=worldcup&m=get_box_reward` | `s1/application/controllers/worldcup.php:140` | `static/js/view/worldcuppop.js:393` |
| `c=worldcup&m=get_pay_chip` | `s1/application/controllers/worldcup.php:521` | `static/js/view/worldcuppop.js:478` |
| `c=worldcup&m=get_pay_chip_info` | `s1/application/controllers/worldcup.php:469` | `static/js/view/worldcuppop.js:450` |
| `c=worldcup&m=get_rank_list` | `s1/application/controllers/worldcup.php:588` | `static/js/view/worldcuppop.js:567` |
| `c=worldcup&m=get_rank_reward` | `s1/application/controllers/worldcup.php:659` | `static/js/view/worldcuppop.js:508` |
| `c=worldcup&m=index` | `s1/application/controllers/worldcup.php:42` | `static/js/view/worldcuppop.js:55` |

### act_kemari 🆕（6）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_kemari&m=action` | `s1/application/controllers/act_kemari.php:209` | `static/js/view/kemaripop.js:715` |
| `c=act_kemari&m=action_lottery` | `s1/application/controllers/act_kemari.php:1629` | `static/js/view/kemaripop.js:350`<br>`static/js/view/kemaripop.js:404` |
| `c=act_kemari&m=get_lottery` | `s1/application/controllers/act_kemari.php:1581` | `static/js/view/kemaripop.js:367` |
| `c=act_kemari&m=index` | `s1/application/controllers/act_kemari.php:78` | `static/js/view/kemaripop.js:38` |
| `c=act_kemari&m=kemari_rank` | `s1/application/controllers/act_kemari.php:843` | `static/js/view/kemaripop.js:647` |
| `c=act_kemari&m=kemari_reward` | `s1/application/controllers/act_kemari.php:1249` | `static/js/view/kemaripop.js:314` |

### chingming 🆕（6）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=chingming&m=action` | `s1/application/controllers/chingming.php:260` | `static/js/view/qingmingpop.js:604` |
| `c=chingming&m=get_achievement_list` | `s1/application/controllers/chingming.php:81` | `static/js/view/qingmingpop.js:320` |
| `c=chingming&m=get_achievement_reward` | `s1/application/controllers/chingming.php:149` | `static/js/view/qingmingpop.js:384` |
| `c=chingming&m=get_rank_list` | `s1/application/controllers/chingming.php:454` | `static/js/view/qingmingpop.js:491` |
| `c=chingming&m=get_rank_reward` | `s1/application/controllers/chingming.php:525` | `static/js/view/qingmingpop.js:425` |
| `c=chingming&m=index` | `s1/application/controllers/chingming.php:43` | `static/js/view/qingmingpop.js:46` |

### login（6）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=login&m=binding_91` | `s1/application/controllers/login.php:423` | `static/js/view/binding91pop.js:140` |
| `c=login&m=user91` | `s1/application/controllers/login.php:58` | `static/js/view/loginworld.js:505` |
| `c=login&m=userdj` | `s1/application/controllers/login.php:122` | `static/js/view/loginworld.js:529` |
| `c=login&m=userdjn` | `s1/application/controllers/login.php:136` | `static/js/view/loginworld.js:541` |
| `c=login&m=userkuaiyong` | `s1/application/controllers/login.php:110` | `static/js/view/loginworld.js:566` |
| `c=login&m=userpp` | `s1/application/controllers/login.php:71` | `static/js/view/loginworld.js:517` |

### pass_general（6）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=pass_general&m=achievement_reward` | `s1/application/controllers/pass_general.php:1350` | `static/js/view/breakthroughworld.js:582` |
| `c=pass_general&m=action_achievement_reward` | `s1/application/controllers/pass_general.php:1528` | `static/js/view/breakthroughworld.js:719` |
| `c=pass_general&m=action_lottery` | `s1/application/controllers/pass_general.php:2058` | `static/js/view/breakthroughworld.js:832`<br>`static/js/view/breakthroughworld.js:889` |
| `c=pass_general&m=action_reward` | `s1/application/controllers/pass_general.php:1787` | `static/js/view/breakthroughworld.js:1062` |
| `c=pass_general&m=lottery_index` | `s1/application/controllers/pass_general.php:2009` | `static/js/view/breakthroughworld.js:848` |
| `c=pass_general&m=reward_info` | `s1/application/controllers/pass_general.php:1658` | `static/js/view/breakthroughworld.js:1101` |

### pay 🆕（6）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=pay&m=create_91_order` | `s1/application/controllers/pay.php:1241` | `static/js/view/miscpop.js:5080` |
| `c=pay&m=create_kuaiyong_order` | `s1/application/controllers/pay.php:1746` | `static/js/view/miscpop.js:5152` |
| `c=pay&m=create_pp_order` | `s1/application/controllers/pay.php:1388` | `static/js/view/miscpop.js:5116` |
| `c=pay&m=get_pay_info` | `s1/application/controllers/pay.php:512` | `static/js/view/miscpop.js:5041` |
| `c=pay&m=get_product_list` | `s1/application/controllers/pay.php:470` | `static/js/view/iospaypop.js:196`<br>`static/js/view/iospaypop.js:265`<br>`static/js/view/iospaypop.js:330`<br>…共 10 处 |
| `c=pay&m=szf_order` | `s1/application/controllers/pay.php:835` | `static/js/view/miscpop.js:5334` |

### siege（6）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=siege&m=battle_prepare` | `s1/application/controllers/siege.php:581` | `static/js/view/countrybattleworld.js:235`<br>`static/js/view/countrybattleworld.js:268` |
| `c=siege&m=capital_info` | `s1/application/controllers/siege.php:99` | `static/js/view/miscpop.js:4133` |
| `c=siege&m=get_report` | `s1/application/controllers/siege.php:1545` | `static/js/view/miscpop.js:3132` |
| `c=siege&m=join_battle` | `s1/application/controllers/siege.php:788` | `static/js/view/countrybattleworld.js:360` |
| `c=siege&m=occupy` | `s1/application/controllers/siege.php:216` | `static/js/view/miscpop.js:4067`<br>`static/js/view/miscpop.js:4075` |
| `c=siege&m=siege_notice` | `s1/application/controllers/siege.php:1596` | `static/js/view/countrywarnoticepop.js:48` |

### act_sevenstar 🆕（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_sevenstar&m=action_light` | — | `static/js/view/sevenstarpop.js:464`<br>`static/js/view/sevenstarpop.js:472` |
| `c=act_sevenstar&m=call_godgeneral` | — | `static/js/view/sevenstarpop.js:309` |
| `c=act_sevenstar&m=get_rank_list` | — | `static/js/view/sevenstarpop.js:335` |
| `c=act_sevenstar&m=get_rank_reward` | — | `static/js/view/sevenstarpop.js:498` |
| `c=act_sevenstar&m=index` | — | `static/js/view/sevenstarpop.js:50` |

### advanced（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=advanced&m=advanced_inherit` | `s1/application/controllers/advanced.php:826` | `static/js/view/advancedpop.js:1122` |
| `c=advanced&m=get_advanced_inherit_info` | `s1/application/controllers/advanced.php:973` | `static/js/view/advancedpop.js:1260` |
| `c=advanced&m=get_general_info` | `s1/application/controllers/advanced.php:247` | `static/js/view/advancedpop.js:594` |
| `c=advanced&m=get_skill_info` | `s1/application/controllers/advanced.php:140` | `static/js/view/advancedpop.js:635` |
| `c=advanced&m=level_up` | `s1/application/controllers/advanced.php:470` | `static/js/view/advancedpop.js:705` |

### equip_forge（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=equip_forge&m=get_equip_attr` | — | `static/js/view/miscpop.js:6858` |
| `c=equip_forge&m=get_fabrication_info` | — | `static/js/view/forgepop.js:686` |
| `c=equip_forge&m=get_suit_attr` | — | `static/js/view/forgepop.js:965` |
| `c=equip_forge&m=resolve_index` | — | `static/js/view/resolvepop.js:38` |
| `c=equip_forge&m=sell` | — | `static/js/view/forgepop.js:622` |

### gmmail 🆕（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=gmmail&m=del_message` | `s1/application/controllers/gmmail.php:135` | `static/js/view/mailboxpop.js:1090`<br>`static/js/view/mailboxpop.js:1130` |
| `c=gmmail&m=get_message` | `s1/application/controllers/gmmail.php:53` | `static/js/view/mailboxpop.js:559` |
| `c=gmmail&m=grade` | `s1/application/controllers/gmmail.php:149` | `static/js/view/mailboxpop.js:374` |
| `c=gmmail&m=index` | `s1/application/controllers/gmmail.php:17` | `static/js/view/mailboxpop.js:887` |
| `c=gmmail&m=send_message` | `s1/application/controllers/gmmail.php:103` | `static/js/view/mailboxpop.js:1013` |

### map（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=map&m=clear_cd` | `s1/application/controllers/map.php:1116` | `static/js/view/cd.js:50`<br>`static/js/view/cd.js:86` |
| `c=map&m=get_mission_reward` | `s1/application/controllers/map.php:1369` | `static/js/view/rushpop.js:130` |
| `c=map&m=get_reward_list` | `s1/application/controllers/map.php:1325` | `static/js/view/rushpop.js:33` |
| `c=map&m=report` | `s1/application/controllers/map.php:1254` | `static/js/view/miscpop.js:613` |
| `c=map&m=report_detail` | `s1/application/controllers/map.php:1296` | `static/js/view/miscpop.js:2377` |

### message（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=message&m=get_new_trends` | `s1/application/controllers/message.php:117` | `static/js/view/trendspop.js:42` |
| `c=message&m=get_notice` | `s1/application/controllers/message.php:75` | `static/js/view/mailboxpop.js:484` |
| `c=message&m=open_gift` | `s1/application/controllers/message.php:465` | `static/js/view/miscpop.js:3215` |
| `c=message&m=update_read_status` | `s1/application/controllers/message.php:542` | `static/js/view/mailpop.js:183` |
| `c=message&m=update_trends_status` | `s1/application/controllers/message.php:148` | `static/js/view/trendspop.js:101` |

### refine（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=refine&m=achieve_index` | `s1/application/controllers/refine.php:1022` | `static/js/view/refineachievepop.js:55` |
| `c=refine&m=get_achieve_info` | `s1/application/controllers/refine.php:1089` | `static/js/view/refineachievepop.js:90` |
| `c=refine&m=item_index` | `s1/application/controllers/refine.php:135` | `static/js/view/equiprefinepop.js:574`<br>`static/js/view/refineinheritpop.js:141` |
| `c=refine&m=item_selected` | `s1/application/controllers/refine.php:252` | `static/js/view/equiprefinepop.js:663` |
| `c=refine&m=master_refresh` | `s1/application/controllers/refine.php:392` | `static/js/view/equiprefinepop.js:822` |

### sanctum（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=sanctum&m=buy_times` | `s1/application/controllers/sanctum.php:648` | `static/js/view/sanctumworld.js:476` |
| `c=sanctum&m=get_mission_info` | `s1/application/controllers/sanctum.php:176` | `static/js/view/sanctumworld.js:393` |
| `c=sanctum&m=get_mission_report_list` | `s1/application/controllers/sanctum.php:702` | `static/js/view/sanctumworld.js:225` |
| `c=sanctum&m=get_report_info` | `s1/application/controllers/sanctum.php:740` | `static/js/view/miscpop.js:3111`<br>`static/js/view/miscpop.js:5682` |
| `c=sanctum&m=select_map` | `s1/application/controllers/sanctum.php:72` | `static/js/view/sanctumworld.js:524`<br>`static/js/view/sanctumworld.js:531` |

### banquet（4）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=banquet&m=dismiss_team` | `s1/application/controllers/banquet.php:325` | `static/js/view/countrybanquetpop.js:489` |
| `c=banquet&m=getout_team` | `s1/application/controllers/banquet.php:382` | `static/js/view/countrybanquetpop.js:700` |
| `c=banquet&m=join_team` | `s1/application/controllers/banquet.php:425` | `static/js/view/countrybanquetpop.js:626` |
| `c=banquet&m=kickout_team` | `s1/application/controllers/banquet.php:253` | `static/js/view/countrybanquetpop.js:666` |

### beatengod（4）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=beatengod&m=get_pay_chip` | `s1/application/controllers/beatengod.php:440` | `static/js/view/beatengodpop.js:477` |
| `c=beatengod&m=get_pay_chip_info` | `s1/application/controllers/beatengod.php:389` | `static/js/view/beatengodpop.js:449` |
| `c=beatengod&m=get_rank_list` | `s1/application/controllers/beatengod.php:496` | `static/js/view/beatengodpop.js:565` |
| `c=beatengod&m=get_rank_reward` | `s1/application/controllers/beatengod.php:560` | `static/js/view/beatengodpop.js:507` |

### epic（4）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=epic&m=buy_lock_times` | `s1/application/controllers/epic.php:483` | `static/js/view/epicpop.js:659` |
| `c=epic&m=buy_refresh_times` | `s1/application/controllers/epic.php:541` | `static/js/view/epicpop.js:703` |
| `c=epic&m=refresh` | `s1/application/controllers/epic.php:172` | `static/js/view/epicpop.js:545` |
| `c=epic&m=select_epic` | `s1/application/controllers/epic.php:109` | `static/js/view/epicpop.js:429` |

### herothrone（4）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=herothrone&m=buy_times` | `s1/application/controllers/herothrone.php:821` | `static/js/view/herothroneworld.js:585` |
| `c=herothrone&m=end_battle` | `s1/application/controllers/herothrone.php:905` | `static/js/view/herothroneworld.js:370` |
| `c=herothrone&m=go_back` | `s1/application/controllers/herothrone.php:118` | `static/js/view/herothroneworld.js:636` |
| `c=herothrone&m=start` | `s1/application/controllers/herothrone.php:185` | `static/js/view/herothroneworld.js:482` |

### act_enrichment 🆕（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_enrichment&m=get_day_reward` | `s1/application/controllers/act_enrichment.php:256` | `static/js/view/enrichmentpop.js:196` |
| `c=act_enrichment&m=index` | `s1/application/controllers/act_enrichment.php:80` | `static/js/view/enrichmentpop.js:38` |
| `c=act_enrichment&m=reward_index` | `s1/application/controllers/act_enrichment.php:170` | `static/js/view/enrichmentpop.js:120` |

### act_halloween（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_halloween&m=action_all_pumpkin` | `s1/application/controllers/act_halloween.php:554` | `static/js/view/halloweenpop.js:296` |
| `c=act_halloween&m=action_medal` | `s1/application/controllers/act_halloween.php:294` | `static/js/view/halloweenpop.js:182` |
| `c=act_halloween&m=medal_index` | `s1/application/controllers/act_halloween.php:224` | `static/js/view/halloweenpop.js:602` |

### act_tanabata 🆕（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_tanabata&m=index` | `s1/application/controllers/act_tanabata.php:127` | `static/js/view/valentinepop.js:37`<br>`static/js/view/valentinepop.js:63` |
| `c=act_tanabata&m=rank_reward` | `s1/application/controllers/act_tanabata.php:413` | `static/js/view/nationcelpop.js:287`<br>`static/js/view/valentinepop.js:331` |
| `c=act_tanabata&m=reward` | `s1/application/controllers/act_tanabata.php:260` | `static/js/view/valentinepop.js:549` |

### act_thirty 🆕（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_thirty&m=action_chip_reward` | `s1/application/controllers/act_thirty.php:422` | `static/js/view/chargegiftpop.js:160` |
| `c=act_thirty&m=action_reward` | `s1/application/controllers/act_thirty.php:225` | `static/js/view/chargegiftpop.js:125` |
| `c=act_thirty&m=index` | `s1/application/controllers/act_thirty.php:86` | `static/js/view/chargegiftpop.js:35` |

### generaltask（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=generaltask&m=buy_number` | `s1/application/controllers/generaltask.php:395` | `static/js/view/generaltaskworld.js:541`<br>`static/js/view/generaltaskworld.js:579` |
| `c=generaltask&m=get_list` | `s1/application/controllers/generaltask.php:79` | `static/js/view/generaltaskworld.js:420` |
| `c=generaltask&m=get_report_list` | `s1/application/controllers/generaltask.php:350` | `static/js/view/generaltaskworld.js:375` |

### island（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=island&m=get_mission` | `s1/application/controllers/island.php:134` | `static/js/view/miscpop.js:1800` |
| `c=island&m=get_report_info` | `s1/application/controllers/island.php:435` | `static/js/view/miscpop.js:2416`<br>`static/js/view/miscpop.js:3118` |
| `c=island&m=get_report_list` | `s1/application/controllers/island.php:408` | `static/js/view/miscpop.js:1781` |

### mount（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=mount&m=get_info_by_id` | `s1/application/controllers/mount.php:195` | `static/js/view/mountpop.js:1036`<br>`static/js/view/mountpop.js:455` |
| `c=mount&m=get_level_info` | `s1/application/controllers/mount.php:362` | `static/js/view/mountpop.js:926` |
| `c=mount&m=get_mount_by_page` | `s1/application/controllers/mount.php:1074` | `static/js/view/mountpop.js:611`<br>`static/js/view/mountpop.js:618` |

### newyeardailysign 🆕（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=newyeardailysign&m=index` | `s1/application/controllers/newyeardailysign.php:48` | `static/js/view/ganenpop.js:27` |
| `c=newyeardailysign&m=open_box` | `s1/application/controllers/newyeardailysign.php:132` | `static/js/view/ganenpop.js:196` |
| `c=newyeardailysign&m=sign` | `s1/application/controllers/newyeardailysign.php:99` | `static/js/view/ganenpop.js:123` |

### overseastrade（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=overseastrade&m=buy` | `s1/application/controllers/overseastrade.php:1653` | `static/js/view/overseastradepop.js:1519` |
| `c=overseastrade&m=get_report_info` | `s1/application/controllers/overseastrade.php:1597` | `static/js/view/miscpop.js:2521` |
| `c=overseastrade&m=rob` | `s1/application/controllers/overseastrade.php:953` | `static/js/view/overseastradepop.js:1452` |

### springlottery 🆕（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=springlottery&m=action` | `s1/application/controllers/springlottery.php:623` | `static/js/view/newyearlotterypop.js:469` |
| `c=springlottery&m=get_reward` | `s1/application/controllers/springlottery.php:1386` | `static/js/view/newyearlotterypop.js:702` |
| `c=springlottery&m=index` | `s1/application/controllers/springlottery.php:62` | `static/js/view/newyearlotterypop.js:46` |

### strengthen（3）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=strengthen&m=achieve_unlock` | `s1/application/controllers/strengthen.php:771` | `static/js/view/strongachievepop.js:264` |
| `c=strengthen&m=achievement` | `s1/application/controllers/strengthen.php:619` | `static/js/view/strongachievepop.js:362`<br>`static/js/view/strongachievepop.js:72` |
| `c=strengthen&m=revert_achieve` | `s1/application/controllers/strengthen.php:929` | `static/js/view/strongachievepop.js:388` |

### act_celebration 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_celebration&m=get_my_reward` | `s1/application/controllers/act_celebration.php:310` | `static/js/view/nationcelpop.js:415` |
| `c=act_celebration&m=index` | `s1/application/controllers/act_celebration.php:128` | `static/js/view/nationcelpop.js:38` |

### act_national_day 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_national_day&m=index` | `s1/application/controllers/act_national_day.php:60` | `static/js/view/nationaldaypop.js:36` |
| `c=act_national_day&m=open_red_packets` | `s1/application/controllers/act_national_day.php:154` | `static/js/view/nationaldaypop.js:143` |

### act_paychecks 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_paychecks&m=get_reward` | `s1/application/controllers/act_paychecks.php:144` | `static/js/view/paycheckspop.js:154` |
| `c=act_paychecks&m=index` | `s1/application/controllers/act_paychecks.php:57` | `static/js/view/paycheckspop.js:23` |

### act_veteran 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_veteran&m=get_reward` | `s1/application/controllers/act_veteran.php:151` | `static/js/view/recallpop.js:205` |
| `c=act_veteran&m=index` | `s1/application/controllers/act_veteran.php:54` | `static/js/view/recallpop.js:33` |

### anniversary 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=anniversary&m=get_reward` | `s1/application/controllers/anniversary.php:84` | `static/js/view/anniversarypop.js:140` |
| `c=anniversary&m=index` | `s1/application/controllers/anniversary.php:35` | `static/js/view/anniversarypop.js:82` |

### chat（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=chat&m=ban_uid` | `s1/application/controllers/chat.php:405` | `static/js/view/chatpop.js:451` |
| `c=chat&m=getuid` | `s1/application/controllers/chat.php:381` | `static/js/view/chatpop.js:711` |

### christmasgift 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=christmasgift&m=get_reward` | `s1/application/controllers/christmasgift.php:230` | `static/js/view/christmasgiftpop.js:359` |
| `c=christmasgift&m=index` | `s1/application/controllers/christmasgift.php:41` | `static/js/view/christmasgiftpop.js:52`<br>`static/js/view/christmasgiftpop.js:86` |

### compete（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=compete&m=buy_pk_num` | `s1/application/controllers/compete.php:376` | `static/js/view/zhulupop.js:356`<br>`static/js/view/zhulupop.js:385` |
| `c=compete&m=get_reward_list` | `s1/application/controllers/compete.php:294` | `static/js/view/zhulupop.js:291` |

### countryshop（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=countryshop&m=red_item_list` | `s1/application/controllers/countryshop.php:219` | `static/js/view/countrytreasurepop.js:297` |
| `c=countryshop&m=sale_red_item` | `s1/application/controllers/countryshop.php:293` | `static/js/view/countrytreasurepop.js:481` |

### elite（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=elite&m=buy_number` | `s1/application/controllers/elite.php:566` | `static/js/view/elitepop.js:1073`<br>`static/js/view/elitepop.js:1101` |
| `c=elite&m=sendinvite` | `s1/application/controllers/elite.php:629` | `static/js/view/elitepop.js:1027` |

### general_book（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=general_book&m=get_achievement_list` | `s1/application/controllers/general_book.php:152` | `static/js/view/heromanualpop.js:416` |
| `c=general_book&m=get_book_list` | `s1/application/controllers/general_book.php:109` | `static/js/view/heromanualpop.js:288` |

### general_only 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=general_only&m=buy` | `s1/application/controllers/general_only.php:177` | `static/js/view/outprintgodpop.js:197` |
| `c=general_only&m=index` | `s1/application/controllers/general_only.php:41` | `static/js/view/outprintgodpop.js:34` |

### general_onsale 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=general_onsale&m=buy` | `s1/application/controllers/general_onsale.php:182` | `static/js/view/godwillpop.js:218` |
| `c=general_onsale&m=index` | `s1/application/controllers/general_onsale.php:31` | `static/js/view/godwillpop.js:43` |

### gvg（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=gvg&m=get_bet` | `s1/application/controllers/gvg.php:467` | `static/js/view/gvgworld.js:682` |
| `c=gvg&m=get_report` | `s1/application/controllers/gvg.php:286` | `static/js/view/gvgworld.js:824` |

### ios（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=ios&m=buylist` | `s1/application/controllers/ios.php:212` | `static/js/view/iospaypop.js:98`<br>`static/js/view/rebatepop.js:95` |
| `c=ios&m=prepay` | `s1/application/controllers/ios.php:246` | `static/js/view/miscpop.js:4285` |

### limitedshop 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=limitedshop&m=buy_general` | `s1/application/controllers/limitedshop.php:129` | `static/js/view/limitedgodpop.js:298` |
| `c=limitedshop&m=index` | `s1/application/controllers/limitedshop.php:30` | `static/js/view/limitedgodpop.js:227`<br>`static/js/view/limitedgodpop.js:48` |

### loginreward 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=loginreward&m=action_reward` | `s1/application/controllers/loginreward.php:204` | `static/js/view/loginrewardpop.js:214` |
| `c=loginreward&m=index` | `s1/application/controllers/loginreward.php:48` | `static/js/view/loginrewardpop.js:35` |

### lottery（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=lottery&m=buy_lottery_num` | `s1/application/controllers/lottery.php:269` | `static/js/view/lotterypop.js:513` |
| `c=lottery&m=refresh` | `s1/application/controllers/lottery.php:333` | `static/js/view/lotterypop.js:270` |

### member（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=member&m=open_gift` | `s1/application/controllers/member.php:1217` | `static/js/view/giftpop.js:123`<br>`static/js/view/taskgiftpop.js:150` |
| `c=member&m=updatemissionsite` | `s1/application/controllers/member.php:1325` | `static/js/view/guide.js:2013` |

### newgift 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=newgift&m=get_reward` | `s1/application/controllers/newgift.php:236` | `static/js/view/newgiftpop.js:363` |
| `c=newgift&m=index` | `s1/application/controllers/newgift.php:51` | `static/js/view/newgiftpop.js:48` |

### newrolereward 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=newrolereward&m=get_reward` | `s1/application/controllers/newrolereward.php:87` | `static/js/view/newbiegiftpop.js:210` |
| `c=newrolereward&m=index` | `s1/application/controllers/newrolereward.php:30` | `static/js/view/newbiegiftpop.js:145` |

### newyearpay 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=newyearpay&m=get_reward` | `s1/application/controllers/newyearpay.php:85` | `static/js/view/newyearpaypop.js:151` |
| `c=newyearpay&m=pay_reward` | `s1/application/controllers/newyearpay.php:37` | `static/js/view/newyearpaypop.js:28` |

### rebate 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=rebate&m=get_reward` | `s1/application/controllers/rebate.php:119` | `static/js/view/bearpop.js:161` |
| `c=rebate&m=index` | `s1/application/controllers/rebate.php:71` | `static/js/view/bearpop.js:40` |

### report 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=report&m=save` | `s1/application/controllers/report.php:20` | `static/js/view/miscpop.js:6287` |
| `c=report&m=send` | — | `static/js/core/base.js:2686` |

### rolegift 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=rolegift&m=get_reward` | `s1/application/controllers/rolegift.php:103` | `static/js/view/lotterypop.js:646` |
| `c=rolegift&m=index` | `s1/application/controllers/rolegift.php:17` | `static/js/view/lotterypop.js:558` |

### sacrifice 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=sacrifice&m=go_sacrifice` | `s1/application/controllers/sacrifice.php:89` | `static/js/view/sacrificepop.js:128` |
| `c=sacrifice&m=index` | `s1/application/controllers/sacrifice.php:30` | `static/js/view/sacrificepop.js:47` |

### smash 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=smash&m=action` | `s1/application/controllers/smash.php:64` | `static/js/view/smashpop.js:127` |
| `c=smash&m=index` | `s1/application/controllers/smash.php:20` | `static/js/view/smashpop.js:33` |

### springshop 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=springshop&m=buy` | `s1/application/controllers/springshop.php:186` | `static/js/view/springshoppop.js:252` |
| `c=springshop&m=index` | `s1/application/controllers/springshop.php:53` | `static/js/view/springshoppop.js:37`<br>`static/js/view/springshoppop.js:96` |

### star（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=star&m=refining` | `s1/application/controllers/star.php:499` | `static/js/view/soulrefinepop.js:99` |
| `c=star&m=refining_index` | `s1/application/controllers/star.php:405` | `static/js/view/soulrefinepop.js:33` |

### clickreward 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=clickreward&m=index` | `s1/application/controllers/clickreward.php:25` | `static/js/view/miscpop.js:6121` |

### countrymine（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=countrymine&m=get_countrymine_info` | `s1/application/controllers/countrymine.php:164` | `static/js/view/countrymineworld.js:700` |

### cultivate（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=cultivate&m=giveup` | `s1/application/controllers/cultivate.php:275` | `static/js/view/fosterpop.js:315` |

### dice（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=dice&m=get_reward` | `s1/application/controllers/dice.php:328` | `static/js/view/countrydicepop.js:390` |

### hitegg（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=hitegg&m=get_hitegg_reward` | `s1/application/controllers/hitegg.php:365` | `static/js/view/eggpop.js:456` |

### iostrack 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=iostrack&m=trackall` | `uc/application/controllers/iostrack.php:293` | `static/js/core/gamestart.js:75` |

### password 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=password&m=set_pwd` | `uc/application/controllers/password.php:87` | `static/js/view/settingspop.js:424` |

### paydj 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=paydj&m=create_djpay_order` | `s1/application/controllers/paydj.php:152` | `static/js/view/miscpop.js:5175` |

### paydjn 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=paydjn&m=create_djpay_order` | `s1/application/controllers/paydjn.php:152` | `static/js/view/miscpop.js:5202` |

### register（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=register&m=binding` | `uc/application/controllers/register.php:185` | `static/js/view/miscpop.js:3666`<br>`static/js/view/regrewardpop.js:122` |

### regreward 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=regreward&m=get_reward` | `s1/application/controllers/regreward.php:46` | `static/js/view/regrewardpop.js:186` |

### talent（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=talent&m=get_info` | `s1/application/controllers/talent.php:288` | `static/js/view/talentpop.js:275` |

### user91 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=user91&m=login_ios` | — | `static/js/view/loginworld.js:851` |

### userdj 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=userdj&m=login_ios` | — | `static/js/view/loginworld.js:879` |

### userdjn 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=userdjn&m=login` | `uc/application/controllers/userdjn.php:17` | `static/js/view/loginworld.js:901` |

### userkuaiyong 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=userkuaiyong&m=login` | — | `static/js/view/loginworld.js:936` |

### war_college（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=war_college&m=use_buff` | `s1/application/controllers/war_college.php:259` | `static/js/view/countrycollegepop.js:428` |

### weibocallback 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=weibocallback&m=index` | — | `static/js/view/miscpop.js:6258` |

### workshop（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=workshop&m=open_workshop_site` | `s1/application/controllers/workshop.php:293` | `static/js/view/workshoppop.js:333`<br>`static/js/view/workshoppop.js:339` |

## 已实现但为写死 / 空壳的接口

方法存在，但不读写 DB / Redis，也不调用其他 Handler，返回值是固定内容。

| 接口 | 现状 |
|---|---|
| `c=pack&m=use_item` | 直接返回「使用成功」。旧版只用于跨服道具（`own_equip` type 11–18，规则见 `world_shop`），随跨服竞技一起补 |
| `c=ios&m=verify`、`c=ios&m=savetoken` | 直接返回成功 |
| `c=shop&m=get_info` | 商品取旧版 `Shop` + `Equip` 模板；`nowmaxtimes`、`cd` 为固定值（不读当日购买次数） |
| `c=overseastrade&m=get_trade_log`、`c=overseastrade&m=item_list` | 固定内容 |
| `c=studfarm&m=index` | 固定内容 |
| `c=practice&m=get_unlock_price` | 固定内容 |
| `c=elite&m=get_server` | 固定内容 |
| `c=user&m=get_info_by_temptoken` | 固定内容 |
| `c=activationcode&m=index` | 固定文案（兑换走 `get_reward`，激活码表为代码内常量） |
| `c=city&m=clear_cd` | 不扣元宝、不清 CD 状态 |

## 已实现但待按旧版重写

接口能用，但玩法、数值或存储不是旧版的；当前战斗不读这些数据。

| 接口 / 位置 | 现状 |
|---|---|
| `c=star`（`StarHandler`） | 待按旧版重写。旧版武将没有逐将升星；星级存 Redis `general_star:{uid}:{gid}`。已不再给 `wuliup`/`tiliup` 加值（培养字段只属于 `cultivate`） |
| `c=talent`（`TalentHandler`） | 待按旧版重写。天赋等级存 Redis `general_talents:{uid}:{gid}`，不是旧版 `talentid` 天赋体系 |
| `c=refine`（`RefineHandler`） | 待按旧版重写。精炼词条存 Redis `equip_refine:{uid}:{eid}`；旧版写 `equipment.hammer`，加成见 `LegacyStats::refineAdd` |
| `c=gem`（`GemHandler`） | 待按旧版重写。宝石存 Redis `user_gems:{uid}`，不是旧版按装备镶嵌 |
| `c=tavern`（`TavernHandler::HERO_POOL`） | 依赖未移植：名将池、价格、展示数值不是旧版 Pub 数据；入库武将按名称+形象对上的模板（52 张飞、33 关羽、51 诸葛亮、6 赵云） |
| `c=lottery&m=action` | 依赖未移植：旧版按 `Lottery` 分组概率抽奖；新版写死转盘的装备格「青钢名剑」在 MasterData 中不存在，抽中不发装备 |
| `c=arena&m=shop_exchange` | 依赖未移植：旧版扣积分后发放；re_type 6 玉石、9 宝石碎片、14~16 精炼石类、17~25 装备精华、26 坐骑、27 将魂、28 宝石的玩家存储新版没有，这些商品返回 -3 不扣积分（`ArenaExchange` 里有 6 / 9 / 26 / 27 / 28 共 15 件） |
| `c=arena`（`ArenaHandler`） | 依赖未移植：聊天室战报（`action` 夺得第 1 名广播、`send_to_chat` 照扣 2 元宝但不广播）、信件战报 `pm_report`（信件存 Redis `user_messages`，`info.report` 为其 id）、主线任务 8 / 9、日常任务 10、`edifice` 首次打开记录、神树 `tree`（恒 0）、消费统计 `stat_model`；酒馆获得日志 `add_pub_log` |
| `c=equip_forge&m=fabrication` | 依赖未移植：旧版打造走 newequip 体系；只有图纸 1 能对上模板（Equip 22 青龙偃月刀），图纸 2、3 不可锻造 |
| `c=map&m=action` 关卡掉落 | 已按旧版 `Mission` 发 gettype 1（装备）/ 3 / 4；依赖未移植：2 装备碎片、5 / 6 天赋卷轴（及碎片）不发放；`times` 刷图未移植（按单次攻击） |
| `c=map&m=get_newresult` | 已按旧版 `Map` 表发 rewardtype 1（装备）；2 天赋卷轴 / 3 声望 / 4 坐骑依赖未移植，返回 -4 |
| `General::createFromTemplate` | 依赖未移植：获得武将气泡（`ongeneral`）、主公 80 级以上名将成就加成重算、主线任务 19 |
| `Equipment::addFromTemplate` | 依赖未移植：碎片分支（旧版 `add_item` `$type = 1`、`itemchip_log`） |
| `c=matrix`（`MatrixHandler`） | 依赖未移植：主线任务 5（阵法升级）/ 6（同时上阵 5 将）、消费统计 `stat_model->matrixlevelup` |
| `c=muster&m=index` / `go_rest` | 依赖未移植：获得武将气泡 `member_field.ongeneral`、武将折旧 CD `member_field.general_sale`（`sale_cd` 按 0）、坐骑表 `own_mount.gid` 清零（新版坐骑在 Redis `user_mounts`） |
| `c=muster&m=sale_general` | 待按旧版重写。新版自定的遣散（按等级发银两、软删除）；旧版为 VIP 7 起的武将折旧（名将榜售价 × VIP 系数、7 天 CD、终止训练、下阵、卸装备 / 将魂 / 坐骑、重算名将成就） |
| `c=practice&m=practice_stop` | 待按旧版重写。新版自定的结算（按训练位倍率发经验）；`c=muster&m=go_rest` 终止训练已用旧版 `practice_stop`（`PracticeHandler::legacyStop`） |

## 战斗

组队（`src/Battle/TeamBuilder.php`：`init_member_team_info` / `init_monster_team_info` / `team_return_arr`）与推演（`src/Battle/BattleEngine.php`：`battle` / `process` / `process_attack` / `process_effect` / `process_position` / `process_hurt` / `_battle_assess`）已按旧版移植。

| 位置 | 现状 |
|---|---|
| 组队加成（`TeamBuilder::forMember`） | 依赖未移植，按 0 计：宝石 `gem_power`、坐骑（主战 100% / 备战 30% 及模板特殊属性）、锻造套装、将魂、主公天赋（91 级）、名将成就（81 级）、美人（60 级）、星魄（80 级）、国家学院（国家 30 级）、名将进阶（100 级）、神树 |
| 推演加成（`BattleEngine`） | 依赖未移植，旧版对应分支不移植：将魂（暴击率 / 闪避 / 识破 / 暴击伤害 / 抗晕 / 生命）、名将成就 / 美人 / 星魄 / 国家学院（暴击率 / 闪避 / 识破 / 暴击伤害）、锻造套装（`suitbuff` 吸血 / 回气 / 偷气 / 清气 / 反伤，最终增伤 / 免伤、暴击伤害、血量伤害倍增、抗晕）、名将进阶（天赋攻防系数、血量）；`leftbuff` / `rightbuff` 的 `tree`（神树）恒为 0、`mount`（坐骑）恒为 `[]` |
| 技能 effect 30 / 31、攻击类型 16 | 旧版 `battle_model.php` 没有对应分支，按旧版照搬：655 袖里乾坤（type 16）没有受击位，656 飞花溅玉、668 地狱人间、669 逆命王轮只按 `ratio` 计伤害；技能描述里的偷气 / 眩晕 / 吸血 / 伤害上限等在这份旧版源码中没有实现 |
| 守军 | 只有 `c=map&m=action` 按旧版取 `Mission` → `Monster`。`elite`、`challenge`、`copies`、`epic`、`pass_general`、`sanctum`、`tower`、`compete`、`countryboss`、`countrymine`、`heaven`、`herothrone`、`island`、`siege`、`worldboss` 的守军数值是新版自定的（`TeamBuilder::unportedUnit`：类型 / 天赋 / 技能 / 武力 / 智力按 0；旧版 `process_hurt` 对没有天赋的防御方不计防御）；`worldboss` / `countryboss` 的鼓舞系数也是新版自定的；旧版对应怪物表见 `TeamBuilder::MONSTER_*` |
| 上述玩法的响应 | 新版自定的结构（顶层 `win` / 奖励 / `message`），按 `assess >= 1` 判胜负，不带战报。客户端按旧版控制器读 `res.battle`（`xGame.loadBattle`）与 `res.info.win` 等：`challenge`（`miscpop.js`）、`compete`（`zhulupop.js`）、`copies`（`pacifypop.js`）、`countryboss` / `worldboss`（`miscpop.js`）、`countrymine`（`countrymineworld.js`）、`heaven`（`heavenworld.js`）、`herothrone`（`herothroneworld.js`）、`island`（`miscpop.js` 的 `c=island&m=pk`）、`sanctum`（`sanctumworld.js`）、`siege`（`countrybattleworld.js`，另读 `battle.battle_ready`）；`pass_general`（`breakthroughworld.js`）读 `is_succ` / `pass_id` 等非战斗字段；`epic&m=force`（`epicpop.js`）读 `member` / `add`，旧版不是战斗；`elite&m=action`、`tower&m=action` 前端没有调用 |
| `c=map&m=mission` / `get_mission_list`，`c=map&m=action` 经验 | 据点名称、等级、经验仍取新版自定的 `MapHandler::getEnemyConfig`，与战斗中的旧版 `Mission` 守军不一致；`get_mission_by_id` 的场景 / 关卡开启校验、攻击 CD、强攻元宝未移植 |

## 数据结构缺口

旧版 `zc_sanguo` 库 632 张表（另有 `zc_sanguo_uc` 14 张、`zc_sanguo_battle` 37 张）；新版 `database/schema.sql` 27 张，其余玩家状态约 90 类存于 Redis JSON 键。字段名取自旧版 `.frm` 文件。

### 已建表的缺失字段

| 新表 ← 旧表 | 旧版有、新版无的字段 |
|---|---|
| `member` ← `member` | `jade`、`gem`、`gemuse`、`refinestone`、`colorstone`、`bloodstone`、`souluse`、`soulpack`、`newpm`、`newmail`、`status`、`statustime`、`rebate_firstpay`、`jointime`、`dateline`、`actdateline`、`levelupexp`、`missionworld`、`firstworld`、`server_reward`、`ndgift`、`channel`、`regchannel`、`guest`、`version`、`appversion`、`crontime`、`gift`、`bind91`、`bind360` |
| `general` ← `own_general` | `disable`（`eid1`~`eid4` 不存储，由 `equipment.generalid` + 旧版 `get_item_site` 派生；其余旧字段已补，获得时从模板复制） |
| `equipment` ← `own_equip` | `glevel`、`disable`、`dateline`（`gid` 即 `generalid`；其余旧字段已补，同一武将同一装配位置由 `uk_general_site` 约束） |
| `member_field` ← `member_field` | `levyall`、`levystatus`、`exercisetimes`、`imposetimes`、`baijintimes`、`zhizuntimes`、`qiangstatus`、`qiangdateline`、`shopdateline`、`actnumber`、`actdateline`、`vip_reward`、`devicetoken`、`soultimes`、`onmine`、`onworkshop`、`oncountrymine`、`ongeneral`、`trends_times`、`newyeardinner`、`free_inherit`、`rebate_dateline`、`rebate_version`、`general_sale`、`advanced_time`、`close_invitation`、`clickreward`、`inherit_dateline`、`stone1_times`~`stone3_times`、`login_day`、`login_dateline`、`levelgift`、`first_recharge_an`、`heaventimes`、`treasure_free`、`macaddr`、`start_time` |
| `general_soul` ← `own_general_soul` | 字段改名 `site1-8` → `slot1-8`（客户端若读 `site*` 需双写） |
| `practice` ← `own_practice` | 字段改名 `gid` → `generalid` |
| `map` | 含义不同：旧版是关卡配置表（`name`/`premise`/`reward*`），新版是玩家进度表 |

部分字段可能已转存 Redis（如 `general_star`、`general_talents`、`user_gems`、`user_mounts`），补字段前先确认是否已有替代存储，避免两处真相。

### 新版无对应存储的旧实体

| 领域 | 旧表 |
|---|---|
| 军团城池 / 国战 | `country_city_report`、`country_fight_notice`（军团本体与 `member_country` 等 8 张表已补；`country_city`、`country_mission` 等配置已在 MasterData） |
| 锻造材料 | `own_materials`、`own_materials_log`（模板 `materials_temp` 已在 MasterData） |
| 将魂 | `own_soul`、`own_soul_mergelog`、`soul*`（关卡/怪物/报告/奖励） |
| 其他玩家资产 | `own_stone`、`own_achievement(_log)`、`own_addition`、`own_star_addition` |
| 邮件 / 私信 | `pm`、`pm_report`、`notice` |
| 货币流水 | `gold_log`、`silver_log`、`jade_log`、`reputation_log`、`itemchip_log`、`debris_log` |
| 跨服（battle 库） | `god_*`、`war_*`、`gvg_*`、`world_arena`、`world_report(_log)`、`world_shop_log` |

### 存储风险

大量玩家资产仅存于 Redis（宝石、坐骑、将星、天赋、背包状态等）。若 Redis 未开启 AOF / RDB 持久化，重启即丢档。

## 建议补齐顺序

0. 旧版配置表已生成到 `src/MasterData/`（`tools/gen_master_data.php`），补玩法时数值一律取自这里，不再手写配置。
1. 战斗：各玩法改用旧版怪物表（`TeamBuilder::forMonsters`）与旧版控制器的响应结构（`res.battle` / `res.info`）。
2. 军团体系的外围依赖：国战（`countryfight`）、税金、谏言、国家庆典等（代码中 `依赖未移植` 注释标出）。
3. 将魂、tower、beauty、gem、heaven 等缺口大的玩法。
4. 跨服 god / war / worldarena（含 `pack.use_item`），节日活动。
