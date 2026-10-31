# 旧版接口缺口清单

对比基线：前端 `public/static/js` 中写死的 `c`/`m` 调用（含单行对象、多行对象、`?c=…&m=…` URL、`x.c = …; x.m = …` 赋值四种写法）vs 新版 `src/Handler` 的 `public static` 方法 vs 旧版 CodeIgniter `wwwroot/{s1,uc,battle,war}/application/controllers/` 下全部控制器（含 `newequip/`、`lc/` 等子目录）。完全动态拼接的调用未覆盖。

- 前端调用：667；已实现：471；缺失：196（旧版有 190，旧版也无 6）
- 「旧版位置」为相对旧服务端 `HJServer/wwwroot/` 的路径；「—」表示四个旧服务里都没有对应方法（多为渠道 SDK 登录），需按前端字段反推。
- 标记 🆕 的控制器在新版中整个 Handler 都不存在。
- 本节由 `tools/scan_legacy_gap.php` 生成，勿手改；其余章节手工维护。

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
| `c=god&m=loading` | `s1/application/controllers/god.php:1088` | `static/js/view/loginworld.js:736` |
| `c=god&m=palace` | `s1/application/controllers/god.php:892` | `static/js/view/palaceheroworld.js:36` |
| `c=god&m=palace_report` | `s1/application/controllers/god.php:926` | `static/js/view/palaceheroworld.js:267` |
| `c=god&m=share_report` | `s1/application/controllers/god.php:818` | `static/js/view/miscpop.js:5516` |
| `c=god&m=support` | `s1/application/controllers/god.php:341` | `static/js/view/championsworld.js:1103` |
| `c=god&m=sync_battle_info` | `s1/application/controllers/god.php:297` | `static/js/view/championsworld.js:1051` |

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
| `c=war&m=share_report` | `s1/application/controllers/war.php:803` | `static/js/view/miscpop.js:5625` |
| `c=war&m=support` | `s1/application/controllers/war.php:328` | `static/js/view/warworld.js:1231` |
| `c=war&m=sync_battle_info` | `s1/application/controllers/war.php:282` | `static/js/view/warworld.js:1179` |

### worldarena（10）

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
| `c=worldarena&m=send_to_chat` | `s1/application/controllers/worldarena.php:1646` | `static/js/view/miscpop.js:4812` |

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
| `c=login&m=user91` | `s1/application/controllers/login.php:58` | `static/js/view/loginworld.js:507` |
| `c=login&m=userdj` | `s1/application/controllers/login.php:122` | `static/js/view/loginworld.js:531` |
| `c=login&m=userdjn` | `s1/application/controllers/login.php:136` | `static/js/view/loginworld.js:543` |
| `c=login&m=userkuaiyong` | `s1/application/controllers/login.php:110` | `static/js/view/loginworld.js:568` |
| `c=login&m=userpp` | `s1/application/controllers/login.php:71` | `static/js/view/loginworld.js:519` |

### act_sevenstar 🆕（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=act_sevenstar&m=action_light` | `s1/application/controllers/lc/act_sevenstar.php:414` | `static/js/view/sevenstarpop.js:464`<br>`static/js/view/sevenstarpop.js:472` |
| `c=act_sevenstar&m=call_godgeneral` | `s1/application/controllers/lc/act_sevenstar.php:326` | `static/js/view/sevenstarpop.js:309` |
| `c=act_sevenstar&m=get_rank_list` | `s1/application/controllers/lc/act_sevenstar.php:223` | `static/js/view/sevenstarpop.js:335` |
| `c=act_sevenstar&m=get_rank_reward` | `s1/application/controllers/lc/act_sevenstar.php:647` | `static/js/view/sevenstarpop.js:498` |
| `c=act_sevenstar&m=index` | `s1/application/controllers/lc/act_sevenstar.php:78` | `static/js/view/sevenstarpop.js:50` |

### equip_forge（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=equip_forge&m=get_equip_attr` | `s1/application/controllers/newequip/equip_forge.php:1725` | `static/js/view/miscpop.js:6860` |
| `c=equip_forge&m=get_fabrication_info` | `s1/application/controllers/newequip/equip_forge.php:264` | `static/js/view/forgepop.js:686` |
| `c=equip_forge&m=get_suit_attr` | `s1/application/controllers/newequip/equip_forge.php:1680` | `static/js/view/forgepop.js:965` |
| `c=equip_forge&m=resolve_index` | `s1/application/controllers/newequip/equip_forge.php:1074` | `static/js/view/resolvepop.js:38` |
| `c=equip_forge&m=sell` | `s1/application/controllers/newequip/equip_forge.php:557` | `static/js/view/forgepop.js:622` |

### pay（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=pay&m=create_91_order` | `s1/application/controllers/pay.php:1241` | `static/js/view/miscpop.js:5081` |
| `c=pay&m=create_kuaiyong_order` | `s1/application/controllers/pay.php:1746` | `static/js/view/miscpop.js:5153` |
| `c=pay&m=create_pp_order` | `s1/application/controllers/pay.php:1388` | `static/js/view/miscpop.js:5117` |
| `c=pay&m=get_pay_info` | `s1/application/controllers/pay.php:512` | `static/js/view/miscpop.js:5042` |
| `c=pay&m=szf_order` | `s1/application/controllers/pay.php:835` | `static/js/view/miscpop.js:5335` |

### beatengod（4）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=beatengod&m=get_pay_chip` | `s1/application/controllers/beatengod.php:440` | `static/js/view/beatengodpop.js:477` |
| `c=beatengod&m=get_pay_chip_info` | `s1/application/controllers/beatengod.php:389` | `static/js/view/beatengodpop.js:449` |
| `c=beatengod&m=get_rank_list` | `s1/application/controllers/beatengod.php:496` | `static/js/view/beatengodpop.js:565` |
| `c=beatengod&m=get_rank_reward` | `s1/application/controllers/beatengod.php:560` | `static/js/view/beatengodpop.js:507` |

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

### christmasgift 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=christmasgift&m=get_reward` | `s1/application/controllers/christmasgift.php:230` | `static/js/view/christmasgiftpop.js:359` |
| `c=christmasgift&m=index` | `s1/application/controllers/christmasgift.php:41` | `static/js/view/christmasgiftpop.js:52`<br>`static/js/view/christmasgiftpop.js:86` |

### countryshop（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=countryshop&m=red_item_list` | `s1/application/controllers/countryshop.php:219` | `static/js/view/countrytreasurepop.js:297` |
| `c=countryshop&m=sale_red_item` | `s1/application/controllers/countryshop.php:293` | `static/js/view/countrytreasurepop.js:481` |

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
| `c=ios&m=prepay` | `s1/application/controllers/ios.php:246` | `static/js/view/miscpop.js:4286` |

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

### report 🆕（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=report&m=save` | `s1/application/controllers/report.php:20` | `static/js/view/miscpop.js:6289` |
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

### clickreward 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=clickreward&m=index` | `s1/application/controllers/clickreward.php:25` | `static/js/view/miscpop.js:6122` |

### dice（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=dice&m=get_reward` | `s1/application/controllers/dice.php:328` | `static/js/view/countrydicepop.js:390` |

### generaltask（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=generaltask&m=send_to_chat` | — | `static/js/view/miscpop.js:4628` |

### hitegg（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=hitegg&m=get_hitegg_reward` | `s1/application/controllers/hitegg.php:365` | `static/js/view/eggpop.js:456` |

### iostrack 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=iostrack&m=trackall` | `uc/application/controllers/iostrack.php:293` | `static/js/core/gamestart.js:75` |

### member（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=member&m=open_gift` | `s1/application/controllers/member.php:1217` | `static/js/view/giftpop.js:123`<br>`static/js/view/taskgiftpop.js:150` |

### password 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=password&m=set_pwd` | `uc/application/controllers/password.php:87` | `static/js/view/settingspop.js:498` |

### paydj 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=paydj&m=create_djpay_order` | `s1/application/controllers/paydj.php:152` | `static/js/view/miscpop.js:5176` |

### paydjn 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=paydjn&m=create_djpay_order` | `s1/application/controllers/paydjn.php:152` | `static/js/view/miscpop.js:5203` |

### register（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=register&m=binding` | `uc/application/controllers/register.php:185` | `static/js/view/miscpop.js:3667`<br>`static/js/view/regrewardpop.js:123` |

### regreward 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=regreward&m=get_reward` | `s1/application/controllers/regreward.php:46` | `static/js/view/regrewardpop.js:187` |

### user91 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=user91&m=login_ios` | — | `static/js/view/loginworld.js:855` |

### userdj 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=userdj&m=login_ios` | — | `static/js/view/loginworld.js:883` |

### userdjn 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=userdjn&m=login` | `uc/application/controllers/userdjn.php:17` | `static/js/view/loginworld.js:905` |

### userkuaiyong 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=userkuaiyong&m=login` | — | `static/js/view/loginworld.js:940` |

### weibocallback 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=weibocallback&m=index` | — | `static/js/view/miscpop.js:6260` |

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
| `c=user&m=get_info_by_temptoken` | 固定内容 |
| `c=activationcode&m=index` | 固定文案（兑换走 `get_reward`，激活码表为代码内常量） |
| `c=city&m=clear_cd` | 不扣元宝、不清 CD 状态 |

## 已实现但待按旧版重写

接口能用，但玩法、数值或存储不是旧版的；当前战斗不读这些数据。

| 接口 / 位置 | 现状 |
|---|---|
| `c=beauty`（`BeautyHandler`、`Model\Beauty`） | 依赖未移植：首次打开记录 `edifice_model->add_edifice_log(11)`、春节送福活动计数（`actnewyear_model->add_task_num`：掠夺 16、亲近 17） |
| `c=advanced`（`AdvancedHandler`、`Model\Advanced`） | 依赖未移植：突飞卡的翻牌活动计数（`tavern_model->sub_freeleap` 的四方来朝 `flop`，旧版数据中已结束）；计划任务 `cron::update_advanced_log`（改配置后同步技能加成，运维用）与后台恢复进阶传承（`recover_inherit`）不移植 |
| 货币流水（`Model\FlowLog`，`Member::deductCurrency` / `addCurrency` / `addReputation` / `subReputation`） | 依赖未移植：`sub_gold` / `sub_act` / `sub_freeleap` 的翻牌活动计数（`flop`，`member_activity.flop*`，随翻牌活动移植；旧版数据中 2016-12-31 已结束）、`_gold_log` 国庆节活动期间的 `national_day_gold_log`（节日活动）。新版自定玩法里直接改 `member` 货币列的写法（征战掉落、国家商城等）不记流水，随各玩法按旧版重写 |
| `c=message`（`MessageHandler`、`Model\Message`） | 依赖未移植：公告 `get_notice`、每日动态 `get_new_trends` / `update_trends_status` 的内容来自 uc 服务器 `inform` 接口（uc 的 `inform_model` 不在旧版代码包里），按 uc 没有返回数据处理；主城的 `notice_id` / `trends_id` 恒为 0；GM 信件的 `channel`（`member.channel` 未移植，记 0）。尚未接入信件的旧版玩法（随各玩法移植）：夺矿 3（`mine`）、海外贸易 9（`overseastrade`）、跨服远征 8（`worldarena`）。不移植：`platform` = android / wp8 的原生客户端文本转换 |
| `c=gmmail`（`GmmailHandler`） | GM 回复（`message_model->reply`，后台功能）不移植：没有后台时 `reply_uid` 恒为 0，`grade` 一律 -3 |
| `c=information`（`InformationHandler`） | `index` / `get_info` / `get_own_achievement` 待按旧版重写（仍是新版自定的展示；旧版 `index` / `get_info` 的国家学院三围 `_get_war_college_info` 随之接入 `WarCollege::applyInfo`） |
| `c=mainquest`（`MainquestHandler`、`Model\MainQuestTask`） | 依赖未移植：「连续登录」（类型 20）读 `member_field.login_day`（`member.php` 的 `_update_continuous_login_day` 未移植），按 0。尚未接入主线计数的旧版玩法（随各玩法移植）：银矿 7、海外贸易 14、通商 17 / 18、连续登录 20（`member` 主城） |
| `c=task`（`TaskHandler`、`Model\DailyTask`） | 尚未接入日常任务计数的旧版玩法（随各玩法移植）：征收 2（`city`）、银矿 3（`mine`）、通商 4（`business`） |
| `c=general_book`（`GeneralBookHandler`、`Model\GeneralBook`） | 依赖未移植：首次打开记录 `edifice_model->add_edifice_log(7)`；计划任务 `cron::update_addition`（导入数据后全服重算，运维用）不移植 |
| `c=chat`（`ChatHandler`、`Model\Chat`、`WebSocket\ChatPush`） | 按旧版移植，GameWS 鉴权后改为 ws 推送。依赖未移植：`index` 的战神上线公告（`god_login`，战神殿 god）、发言的神树 `tree`（恒 0）、`member.status` 3 / 4 封号的全局 403（只在发言时认禁言 1 / 2 与到期清除）、后台 / 计划任务喇叭（`horn` 表，`cron::date_horn` / `cron_horn` / `crontab_horn` / `send_horn`，运维功能）与计划任务 `cron::game_process` 的定时系统提示。不移植：`platform` = android / wp8 的原生客户端格式转换、`send_system_chat`（旧版不校验身份）、`clear_ban_key`（运维口令接口）、`illegal_chat` 记录（旧版调用已注释）。与旧版的差别：屏蔽字与重复发言按还原后的原文判断（旧版对 htmlspecialchars 后的内容判断，`&quot;` 含屏蔽字 q，带双引号的消息都被当成违规）；屏蔽玩家改为每人一个有序集合 `chat_ban_{uid}`（旧版每对一个键、`KEYS chat_ban__{uid}*` 会把 uid 前缀相同的玩家算进来）；`read` 过滤后重排下标（旧版 `unset` 留洞，JSON 变成对象） |
| 聊天室广播（其余玩法） | 依赖未移植（所在玩法是新版自定的，随旧版玩法移植）：累计登录 / 砸金蛋 / 黑市的 7 星以上武将小喇叭（`general_get` 等，砸金蛋 22、黑市 14）、幸运大转盘 6 / 7、通商 / 切石 / 打造 / 暴打名将的小喇叭、国家祭祀 9、好友在线状态（`get_online_list`）；跨服 `worldarena&m=send_to_chat`（uc 战报缓存）与 `generaltask&m=send_to_chat`（旧版没有） |
| `c=lottery&m=action` | 依赖未移植：旧版按 `Lottery` 分组概率抽奖；新版写死转盘的装备格「青钢名剑」在 MasterData 中不存在，抽中不发装备 |
| `c=arena`（`ArenaHandler`） | 依赖未移植：`edifice` 首次打开记录、神树 `tree`（恒 0） |
| `c=elite`（`EliteHandler`） | 依赖未移植：春节送福计数、四方来朝（翻牌）元宝计数。实时组队层见下一行 |
| 多人副本实时组队（`EliteRoom`） | 旧版实时服务器不在旧版代码包里，协议按 `elitepop.js` 还原，以下是客户端看不出旧版做法、按新版自定的部分：队伍名取队长昵称；不在开放时间登录回 `loginstatus` -3、服务端内部错误（加锁超时 / 异常）回各 `*_error` -9（客户端都落到 default 提示）；开战最少 2 人（按旧版 `action` 去重后只有 1 人不能开战；客户端语言键名为 `only_3_begin_battle`，旧版是否要求满 3 人未知）；创建 / 加入队伍不在服务端校验次数、VIP、元宝（客户端先校验，开战时 `fight` 校验）；`login` 的 `serverid` 不参与分组（新版单服）；`userin` / `userlogout` / `testmsg` 不发（客户端只打印日志）。`c=elite&m=action` 只供 `startfight` 内部调用，HTTP 返回 `{status: 0, info: []}`（旧版可由任何持 token 的调用方直接开战） |
| `c=equip_forge&m=fabrication` | 依赖未移植：旧版打造走 newequip 体系；只有图纸 1 能对上模板（Equip 22 青龙偃月刀），图纸 2、3 不可锻造 |
| `c=map`（`MapHandler`、`Model\OpenMap`） | 依赖未移植：主公 61 级以上升级的全服福利（`worldarena_model->get_last_reward`，跨服）、主公升级的等级礼包标识（`member_model->level_gift`，`c=levelgift` 是新版自定的）；维护计划任务 `cron::clear_mission_report`（只保留每关最近 7 条首杀战报，旧版代码包里没有执行时间） |
| `c=worldboss`（`WorldBossHandler`） | 计划任务 `cron_acttask::worldboss` 的执行时间旧版代码包里没有，按键过期时间推定为每日 00:30（`WorldBossCron::RUN_AT`） |
| `c=challenge&m=action`（`ChallengeHandler`） | 依赖未移植：春节送福活动计数（`actnewyear_model->add_task_num(version, 15)`） |
| `c=pass_general`（`PassGeneralHandler`、`PassGeneralCron`） | 活动时间取 `ActivityTime`（2016-12-31 00:00 已结束，接口一律 -1） |
| `c=herothrone`（`HerothroneHandler`） | 依赖未移植：uc 服务器跨服排行（`uc_model->get_top_rank` / `update_top_rank`，uc 的 `herothrone_model` 不在旧版代码包里；`index` / `go_back` 的 `list` 恒为空，过关不上报） |
| `c=island`（`IslandHandler`） | 依赖未移植：维护计划任务 `cron::clear_island_report`（只保留每关最近 7 条战报，旧版代码包里没有执行时间） |
| `c=compete`（`CompeteHandler`、`CompeteCron`） | 活动时间取 `ActivityTime`（compete 2016-12-31 00:00 已结束，接口一律 -1）。依赖未移植：神树 `tree`（恒 0）。计划任务 `cron::get_compete_reward` 的执行时间旧版代码包里没有，按每小时执行（只在活动结束后 10 小时内发放） |
| `c=countrymine`（`CountryMineHandler`） | 依赖未移植：好友 `friend_model`（`friend` 恒为 0） |
| `c=siege`（`SiegeHandler`、`SiegeCron`） | 依赖未移植：都城效果 type 2 通商次数 `member_activity.freebusiness`。计划任务 `cron::siege_battle` / `clear_warstatus` 的执行时间旧版代码包里没有，按旧版逻辑推定为每分钟 / 每周六 00:00。都城效果只接入了将魂星路（4）、四象圣域（5）、铜雀台互动次数（6）、通天塔（7）与征收次数（1，`getCityEffect` 内）；玉石作坊（3）、通商（2）与 `member_model` 每日征收重置调用 `get_city_effect` 的玩法尚未按旧版移植 |
| 支付渠道（`c=pay` 下单 / 回调） | 待定渠道（渠道待开发者确定）：客户端调用的 `get_pay_info`（支付宝下单签名）、`create_91_order`、`create_pp_order`、`create_kuaiyong_order`、`szf_order`，以及旧版只由渠道服务器回调的 `confirm_alipay_order` / `confirm_91_order` / `confirm_pp_order` / `szf_confirm` / `googlepay_order` / `googlepay_confirm` / `elex_*` / `*_sf_*` / `*_kuaiyong_order`、网站充值 `create_prepay_order` / `complete_alipay_order` / `index`，与 `paydj` / `paydjn` / `pay360n` / `payaz` / `paydk` / `payhw` / `paytstore*` / `payuc` / `payxiaomi` / `payyidongyun` / `payyolo` 各渠道控制器、`c=ios&m=buylist` / `prepay`（苹果内购）。接入时验签通过后调用 `Model\Pay::complete`；开发者测试用 `tools/pay_grant.php` |
| `c=pay`（`PayHandler`、`Model\Pay`、`Model\Vip`） | 依赖未移植：充值类活动记账——圣诞 / 春节 `christmas_model->add_christmas_log`、暴打名将 `beatengod_model->add_beatengod_pay_order`、我爱世界杯 `worldcup_model->add_beatengod_pay_order`、充值送豪奖 `actthirty_model->add_thirty_pay_order`、`pay_act_init` 的七夕 `acttanabata`、国家庆典 `actcelebration`、中秋 `moon`、发工资 `actpaychecks`、貔貅聚财 `actenrichment`（旧版数据中均已结束）；`vip_model->pay` 的赌石免费次数（`member_field.stone1~3_times`）、清 cd 的强征 / 征收状态（`qiangdateline` / `qiangstatus` / `levystatus`）；首充 `member_field.first_recharge_an`（安卓客户端标识，主城恒回 0） |
| `c=vipwage`（`VipwageHandler`） | 依赖未移植：春节送福活动计数 `actnewyear_model->add_task_num(version, 25)` |
| `c=tavern`、`c=practice`、`c=general_inherit` | 依赖未移植：首次打开记录 `edifice_model->add_edifice_log`（酒馆 5 / 英雄祭坛 9 / 普通酒馆 10、校场 1、武将继承 4）；突飞卡（`Model\FreeLeap`）的翻牌活动计数（四方来朝 `flop`，旧版数据中已结束）；武将继承的老兵召回免费至尊继承（`recall`，`ActivityTime` 中 2016-12-31 已结束，`actveteran_model` 未移植） |
| `c=drink`、`c=banquet`（`DrinkHandler`、`BanquetHandler`、`Model\Banquet`） | 依赖未移植：春节送福活动计数（`actnewyear_model->add_task_num`：对酒 21、国宴 22）；国宴开宴后的国家计划任务标志位 `country_model->set_flog`（消费方 `cron_country` 未移植） |
| `c=expostulation`（`ExpostulationHandler`、`Model\Expostulation`） | 尚未接入谏言任务计数的玩法（新版是自定玩法，随旧版移植）：征收 5（`city&m=impose` / `impress`）、通商 6（`business`）。已接入：强化 1、武将升级 2（`General::addExp`）、培养 3、贸易 4、征战主线 7 |
| `c=war_college`（`WarCollegeHandler`、`Model\WarCollege`） | 不移植：`level_up` 信件的 `platform` = android 原生客户端文本。MasterData `WarCollegeSkillInfo` / `WarCollegeGoldInfo` 是旧服各国的运行数据（不是配置），不读；新服各国的学院技能与税金从国家第一次打开学院时建档 |

## 战斗

组队（`src/Battle/TeamBuilder.php`：`init_member_team_info` / `init_monster_team_info` / `team_return_arr`）与推演（`src/Battle/BattleEngine.php`：`battle` / `process` / `process_attack` / `process_effect` / `process_position` / `process_hurt` / `_battle_assess`）已按旧版移植。团战 `battle_rush`（`src/Battle/BattleRush.php`）已移植 PVP（type 0）、PVE（type 1 国战怪物 / 2 多人副本）与 EVP（type 3 怪物攻城）。

| 位置 | 现状 |
|---|---|
| 组队加成（`TeamBuilder::forMember`） | 依赖未移植，按 0 计：锻造套装、神树 |
| 推演加成（`BattleEngine`） | 依赖未移植，旧版对应分支不移植：锻造套装（`suitbuff` 吸血 / 回气 / 偷气 / 清气 / 反伤，最终增伤 / 免伤、暴击伤害、血量伤害倍增、抗晕、血量%；坐骑模板的血量% / 抗晕 / 免伤 / 吸血已计入）；`leftbuff` / `rightbuff` 的 `tree`（神树）恒为 0 |
| 技能 effect 30 / 31、攻击类型 16 | 旧版 `battle_model.php` 没有对应分支，按旧版照搬：655 袖里乾坤（type 16）没有受击位，656 飞花溅玉、668 地狱人间、669 逆命王轮只按 `ratio` 计伤害；技能描述里的偷气 / 眩晕 / 吸血 / 伤害上限等在这份旧版源码中没有实现 |
| 守军 | 只有 `c=map&m=action` 按旧版取 `Mission` → `Monster`、`c=tower&m=pk` 按旧版取 `SoulMission` → `SoulMonster`、`c=copies&m=pk` 按旧版取 `CopiesInfo`（怪物行的 position1~9 → `CopiesInfo` 的行）、`c=elite&m=action` 按旧版团战取 `EliteMission` → `EliteMonster`、`c=generaltask&m=action` 按旧版 `battle_general` 取 `GeneraltaskMission` → `GeneraltaskMonster`（玩家只上一名武将），`c=sanctum&m=action` / `c=heaven&m=pk` / `c=herothrone&m=action` / `c=island&m=pk` 按旧版 `battle_mission` 取 `SanctumMission` → `SanctumMonster` / `HeavenMission` → `HeavenMonster` / `HerothroneMission` → `HerothroneMonster` / `Island` → `IslandMonster`。`c=worldboss&m=battle` / `c=countryboss&m=battle` 按旧版 `battle_worldboss` 取 `WorldBoss` 行 1 / 2 + 写死数值（`TeamBuilder::forBoss`）。`c=compete&m=action`、`c=countrymine&m=rob`、`c=siege&m=occupy`（至尊攻城）按旧版 `battle_pk` 打玩家，国战按旧版团战取玩家或 `CountryMission` → `CountryMonster` |

## 数据结构缺口

旧版 `zc_sanguo` 库 632 张表（另有 `zc_sanguo_uc` 14 张、`zc_sanguo_battle` 37 张）；新版 `database/schema.sql` 141 张，其余玩家状态约 90 类存于 Redis JSON 键。字段名取自旧版 `.frm` 文件。

### 已建表的缺失字段

| 新表 ← 旧表 | 旧版有、新版无的字段 |
|---|---|
| `member` ← `member` | `status`、`statustime`、`jointime`、`dateline`、`actdateline`、`levelupexp`、`server_reward`、`ndgift`、`channel`、`regchannel`、`guest`、`version`、`appversion`、`crontime`、`gift`、`bind91`、`bind360` |
| `general` ← `own_general` | `disable`（`eid1`~`eid4` 不存储，由 `equipment.generalid` + 旧版 `get_item_site` 派生；其余旧字段已补，获得时从模板复制） |
| `equipment` ← `own_equip` | `glevel`、`disable`、`dateline`（`gid` 即 `generalid`；其余旧字段已补，同一武将同一装配位置由 `uk_general_site` 约束） |
| `member_field` ← `member_field` | `levyall`、`levystatus`、`exercisetimes`、`imposetimes`、`qiangstatus`、`qiangdateline`、`shopdateline`、`actnumber`、`actdateline`、`vip_reward`、`devicetoken`、`onmine`、`onworkshop`、`trends_times`、`newyeardinner`、`close_invitation`、`clickreward`、`stone1_times`~`stone3_times`、`login_day`、`login_dateline`、`levelgift`、`first_recharge_an`、`treasure_free`、`macaddr` |
| `member_activity` ← `member_activity` | 只有将魂星路字段（`towerlevel`、`towerstage`、`towermission`、`freetower`、`towertimes`、`souldate`）、多人副本字段（`elitetimes`、`elitebuytimes`、`elitebuydateline`、`elitesurplustimes`）、金银岛字段（`islandid`、`islandtimes`、`islanddate`）、四象圣域 `sanctumdate`、通天塔字段（`heavenstage`、`heavenmission`、`heavenlevel`、`heaventimes`、`freeheaven`、`heavendate`）、VIP 工资 `vipwage_dateline`、日常任务 `task_number`、每日对酒 `drinktime` 与神将之门 `generaltasktimes`；缺 `sacrifice1`~`sacrifice3`、`exercise_gift`、`exercise_level`、`freebusiness`、`businesstimes`、`towersite`、`treasuremap`、`flopgold`、`flopleap`、`flopact`、`floptimes`、`flopgoldtimes`、`flopacttimes`、`flopleaptimes`、`freemooncake`、`mooncaketimes`、`mapreward`、`jadetype`、`jadenum`、`jadeturn`、`smashtime`（`Member::dailyCron` 只重置 `islandtimes`、`freetower`、`elitetimes`、`generaltasktimes`、`freeheaven`、`task_number`） |
| `practice` ← `own_practice` | 字段改名 `gid` → `generalid` |

部分字段可能已转存 Redis，补字段前先确认是否已有替代存储，避免两处真相。

### 新版无对应存储的旧实体

| 领域 | 旧表 |
|---|---|
| 其他玩家资产 | `own_stone`（赌石玩法的原石记录，与精炼石等三种石头无关） |
| 公告 | `notice`（旧版公告取自 uc 服务器 `inform`，本服 `notice` 表只有后台读写） |
| 跨服（battle 库） | `god_*`、`war_*`、`gvg_*`、`world_arena`、`world_report(_log)`、`world_shop_log` |

### 存储风险

大量玩家资产仅存于 Redis（背包状态等）。若 Redis 未开启 AOF / RDB 持久化，重启即丢档。

## 建议补齐顺序

0. 旧版配置表已生成到 `src/MasterData/`（`tools/gen_master_data.php`），补玩法时数值一律取自这里，不再手写配置。
1. 战斗：各玩法改用旧版怪物表（`TeamBuilder::forMonsters`）与旧版控制器的响应结构（`res.battle` / `res.info`）。
2. 军团体系的外围依赖：税金、国家庆典等（代码中 `依赖未移植` 注释标出）。
3. 其余缺口大的玩法（见「缺失接口」）。
4. 跨服 god / war / worldarena（含 `pack.use_item`），节日活动。
