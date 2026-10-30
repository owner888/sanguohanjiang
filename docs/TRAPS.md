# TRAPS.md — 踩坑与避坑规范

## 1. 主公经验条消失与军令分母为零 (levelupexp & act_limit)

### 症状
- 客户端主界面顶部主公头像右侧无蓝色经验条，经验文本显示为 `1910/0`（分母为 0），军令显示为 `740/0`（分母为 0）。
- 原版客户端在升级经验 > 10000 时自动采用百分比展示（如 `93%`）并正确填充蓝色经验条进度。

### 根因
- 前端 `public/static/js/core/player.js` 的 `player.info` 字段契约为 `levelupexp`（主公升级所需经验）和 `act_limit`（军令上限）。
- 服务端 `MemberHandler::index` 返回数据包时仅输出了 `maxexp` 和 `maxact`，未包含 `levelupexp` 与 `act_limit`。
- 前端 `updateinfo` 遍历更新时忽略未知 key，导致 `player.info.levelupexp` 与 `act_limit` 永远为初始值 `0`。
- `player._expBar()` 计算进度条长度 `parseInt(exp) / parseInt(levelupexp) * expBarLength` 时除以 0 产生 `Infinity`，且因 `levelupexp <= 10000` 被错误移除了 `.toolong` 类名，导致直接将 `exp/0` 渲染到界面上且进度条宽度为 0。

### 避坑规则
1. **老协议客户端契约一致性铁律**：所有向客户端下发的玩家模型字段，必须严格参照老原版 controller/model 命名（`levelupexp`、`act_limit`）。新命名可作为别名共存双写，严禁单方面重命名核心字段。
2. 主公经验与武将经验公式统一遵循原版：`80 * level^2`（1级为 80）。
3. 军令上限依 VIP 等级由 `Member::getActLimit($vip)` 统一计算（VIP0~5 分别对应 50, 60, 70, 80, 90, 100）。

### 示例
```php
$level = max(1, (int)$member['level']);
$vip = max(0, (int)$member['vip']);
$levelUpExp = (int)($member['maxexp'] ?? 0) > 0 ? (int)$member['maxexp'] : Member::getLevelUpExp($level);
$actLimit = Member::getActLimit($vip);

$data = [
    // ...
    'act' => (int)$member['act'],
    'act_limit' => $actLimit,
    'maxact' => $actLimit,
    'exp' => (int)$member['exp'],
    'levelupexp' => $levelUpExp,
    'maxexp' => $levelUpExp,
];
```

## 2. 强化界面装备显示 undefined (hammer & hammer_quality)

### 症状
- 强化弹窗（`strongpop`）中 9 宫格装备列表里，每个装备框底部显示黑色文字 `undefined`。

### 根因
- 前端 `public/static/js/view/strongpop.js` 模板渲染逻辑：
  `'<div class="hammer hammer' + item.hammer_quality + '">' + (item.hammer == 0 ? '' : item.hammer) + '</div>'`
- 服务端 `StrengthenHandler::index` 返回数据时，缺失了 `hammer` 与 `hammer_quality` 字段。
- 在 JavaScript 中，`undefined == 0` 为 `false`，三元表达式直接返回了 `item.hammer` 即 `undefined`，同时产生 `<div class="hammer hammerundefined">undefined</div>` 挂载在装备框底部。
- 此外，未穿戴装备若被设置了 `'generalname' => ''`，也会在 `item.generalname != undefined` 判断中因 `"" != undefined` 产生空 `<p class="name"></p>` 节点。

### 避坑规则
1. 装备类接口下发列表时，必须默认包含精炼字段 `'hammer' => 0, 'hammer_quality' => 0`。
2. 武将穿戴名称 `generalname` 仅在装备实际被武将穿戴（`gid > 0` 且存在武将）时设置；未穿戴时不设置该 key。

### 示例
```php
$itemData = [
    'id' => $eid,
    'equipid' => (int)$item['equipid'],
    'name' => $item['name'],
    'type' => (int)$item['type'],
    'quality' => (int)$item['quality'],
    'level' => $curLevel,
    'gid' => $gid,
    'hammer' => 0,
    'hammer_quality' => 0,
    // ...
];
if ($gid > 0 && isset($generalMap[$gid])) {
    $itemData['generalname'] = $generalMap[$gid];
    $itemData['generalid'] = $gid;
}
```

## 3. 武将头像错乱为杂兵与布阵界面克隆武将 (image ID 错位与 teampop 越界)

### 症状
- 诸葛亮在阵型和将领面板显示为戴蓝缨铁盔的将士，关羽显示为红缨杂将，张飞显示为戴斗笠老翁；
- 布阵（`teampop`）备选列表底部意外多出一个克隆的“张飞”（显示两个张飞）。

### 根因
1. **头像资源与 ID 错位**：
   - 原版资源 `public/static/img/avatar/` 中：
     - 诸葛亮对应 `76.png`（八卦纶巾孔明）
     - 关羽对应 `58.png`（绿袍美髯公）
     - 张飞对应 `54.png`（红巾黑须怒目张翼德）
     - 赵云对应 `25.png`（白银盔红缨赵子龙）
     - 女性主公对应 `2.png`（紫发仙女），男性主公为 `1.png`
   - 服务端此前未维护原版头像字典，直接回退了临时自定义的 `generalid`（10~13），导致分别错读了 `10.png`（斗笠老翁）、`11.png`（杂将）、`12.png`（守卫将士）。
2. **布阵 JS 越界 fallback**：
   - `teampop.js` 的 `showHero` 中包含历史遗留写法：
     `avatar = (hero[i] !== undefined) ? hero[i] : (hero[i - 1] !== undefined ? hero[i - 1] : null);`
   - 当服务端返回 1-based 数组且只有 5 个武将时，循环到 `i=6` 会错误读取 `hero[5]`（张飞），导致第 6 格凭空克隆出一个张飞。

### 避坑规则
1. 所有名将必须通过 `GeneralHandler::HERO_CONFIGS` 原型字典严格绑定原版 `image` 编号。
2. 布阵列表遍历统一规范化为数组线性迭代 `for (var i = 0; i < 8; i++) avatar = heroList[i] || null;`，严禁跨索引兜底。

## 4. 战斗场景武将模型错乱 (MapHandler 推图战场下发临时 image)

### 症状
- 战斗中人物模型与名字完全颠倒：诸葛亮变成金盔大将、关羽变成红缨步兵、张飞变成道士（张角模型）、赵云变成持矛铁盔杂兵；
- 主角（紫之柔）在战斗中变成拿弓箭的小兵。

### 根因
- 前端战斗场景通过 `xGame.hero(image, 1) => img/hero/{image}.png` 渲染武将站立模型。
- `MapHandler::action` 在组装 `leftteam` 战斗队伍时，名将模型被硬编码成了哈希取模算法：
  `'image' => (int)$g['generalid'] === 1 ? $leftHeroImage : (string)((int)$g['generalid'] % 20 + 10)`
  - 诸葛亮 (gid=12) 算得 `12 % 20 + 10 = 22`，对应 `22.png`（金盔大将）；
  - 关羽 (gid=11) 算得 `21`，对应 `21.png`（红缨步兵）；
  - 张飞 (gid=10) 算得 `20`，对应 `20.png`（黄袍道士张角）；
  - 赵云 (gid=13) 算得 `23`，对应 `23.png`（持矛铁盔杂兵）；
  - 主角 (generalid=1) 被传了 `gender=2`，而 `2.png` 刚好是绿衣弓手（`1.png` 才是首发主角廖化刀客）。

### 避坑规则
1. 战斗队伍 (`leftteam`/`rightteam`) 的 `image` 字段严禁做任何公式取模篡改，必须统一走 `GeneralHandler::resolveHeroImage($heroName, ...)` 取得正统模型编号。
2. 首发主公将领在原版中固定绑定为刀客将军廖化模型（`1.png`），非小兵弓手。

## 5. 推图据点经验与通关战报结算分歧 (首次通关与重复扫荡结算公式分流)

### 症状
- 据点信息弹窗显示“主公经验：+830(首杀)，将领经验：+1050(首杀)”，但打赢之后战报弹窗只给了 `+440` 与 `+660`，数值对不上。

### 根因
- 前端据点弹窗（`c=map&m=mission`）固定根据 `first` 状态显示首杀经验还是日常经验。
- 原版逻辑中，当据点尚未通关（`first = 1`）时，下发的奖励为首通经验（`firstkillexp` = 150 + 等级 * 20，`gfirstkillexp` = 200 + 等级 * 25）。
- 但在发起战斗结算接口（`c=map&m=action`）中，此前硬编码了固定常数算式 `100 + 等级 * 10` 和 `150 + 等级 * 15`，未检查玩家是否已通关该据点。
- 导致打未通关据点时：据点弹窗展示了首杀经验（以 Lv.34 为例：150 + 34 * 20 = 830，将领 200 + 34 * 25 = 1050），打完结算却按日常公式发了 100 + 34 * 10 = 440 和 150 + 34 * 15 = 660。

### 避坑规则
- 据点弹窗与战斗结算必须通过 `zc_sanguo_map` 的通关进度（`mission >= current_mission_id`）严格保持状态一致：
  - 首杀（`first = 1`）：统一结算发放 `firstkillexp` 与 `gfirstkillexp`；
  - 非首杀（`first = -1`）：统一结算发放日常经验 `exp` 与 `gexp`，若主公高于怪物等级 5 级以上则按原版设定获得 0 主公经验。

## 6. 战斗失败战报错误显示与发放经验银两 (战败零收益铁律)

### 症状
- 战败（小败/大败）时，战后结算弹窗依然显示“获得经验 +950，将领经验 +1200，获得银币 +500”。

### 根因
- 原版《悍将三国》中，推图战败属于挑战失败，**不增加任何关卡进度、不发放任何经验和银两**（仅消耗 1 点军令用于尝试）。
- 服务端 `MapHandler::action` 在组装 `resultInfo` 时，未将失败分支的 `exp`、`gexp` 与 `silver` 清零，依然下发了胜利时的结算参数；且对玩家账号也执行了 `(int)round($gainExp * 0.2)` 的兜底经验增加。
- 前端 `miscpop.js` 只要收到非 0 的 `exp` 和 `silver`，就会不管胜负一律渲染加成数值并弹出元宝图标。

### 避坑规则
- 战败必须执行**严格零收益**：
  - `exp = 0, gexp = 0, silver = 0, gettype = 0, getvalue = 0`；
  - 玩家账号与将领经验在战败时不增加，战报弹窗将干净显示小败界面，不出现虚假的经验与金币奖励。

## 7. 游戏音频无声与设置弹窗音乐开关双重取反踩踏 Bug

### 症状
- 点击“设置”弹窗中的“开启音乐”按钮后，屏幕弹出 Toast 提示“已开启背景音乐”，但按钮文本没有变成“关闭音乐”（依然停留在“开启音乐”），同时完全没有声音播放；
- 移动端或 Safari 浏览器下音频流加载停滞或无法播放。

### 根因
1. **事件双重监听与 1ms 双重取反**：`settingspop.js` 同时存在局部 `#music_btn` 绑定与全局 `$(document).delegate('#music_btn', ...)`，且事件类型混合了 `click touchend`。在鼠标点击或触屏抬起时先后触发 `touchend` 与 `click`，导致状态在 1ms 内先翻转为 `true`（弹出开启提示），紧接着被全局 delegate 无防抖地翻转回 `false`（强行 `stopMusic()` 并将文案改回“开启音乐”）；
2. **试玩/未绑定账号导致 `servername` 为 null 引发未捕获异常**：`settingspop.js` 中 `$(el).html(servername)` 在 `servername` 为 null 时调用 `null.replace(...)` 抛出 `TypeError`，打断后续事件绑定；
3. **HTTP 静态服务缺乏 206 Partial Content 支持**：Safari / iOS WebKit 的媒体播放强制依赖 HTTP Byte-Range 请求（`Range: bytes=0-1`），返回 200 会导致流媒体被挂起。

### 避坑规则
- 弹窗按钮事件坚决避免局部绑定与全局 `delegate` 重复挂载；触控与点击混合事件必须施加严格的防抖与状态互斥；
- 统一使用 `.text()` 安全填充用户信息，防御 null/undefined 避免打断后续 UI 管道；
- 服务端媒体分段资源必须严格提供 `Accept-Ranges: bytes` 与标准的 `206 Partial Content` 响应。







## 8. 导入 schema.sql 中文乱码 (mysql 客户端字符集)

### 症状
- 新建库后跑 PHPUnit，`testSiegeSystem` 等断言中文名称的用例失败，库里的中文默认值 / 注释是乱码。

### 根因
- `mysql` 客户端默认连接字符集不是 utf8mb4，按错误编码把 `schema.sql` 里的中文字面量写进库。

### 避坑规则
- 导入 `database/schema.sql` 与 `database/migrations/*.sql` 一律带 `--default-character-set=utf8mb4`。

### 示例
```bash
mysql --default-character-set=utf8mb4 -uroot -p < database/schema.sql
```
