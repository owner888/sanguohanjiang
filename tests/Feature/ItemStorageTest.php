<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\Gem;
use Hanjiang\MasterData\MountLevel;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\Soul;
use Hanjiang\MasterData\SoulType;
use Hanjiang\MasterData\TalentScroll;
use Hanjiang\Model\CountryDebris;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use PHPUnit\Framework\TestCase;

/**
 * 物品存储模型（玉石、酒馆宝石、石头、宝石与碎片、将魂、坐骑与碎片、装备碎片 / 天赋卷轴 / 装备精华、神器碎片）
 *
 * 每个用例新建一个只有 member 行的玩家（等级 50、VIP 3、将魂包裹 4 格），直接调模型，对照旧版语义与流水表。
 */
final class ItemStorageTest extends TestCase
{
    private const int NOW = 1793000000;

    private function player(): int
    {
        $uid = (int)DB::query('SELECT GREATEST(COALESCE(MAX(`uid`), 0) + 1, 800000) AS `n` FROM `zc_sanguo_member`')[0]['n'];
        DB::table('member')->insert([
            'uid' => $uid,
            'username' => 'storage_' . $uid,
            'nickname' => '物品测试' . $uid,
            'level' => 50,
            'vip' => 3,
            'soulpack' => 4,
        ]);
        return $uid;
    }

    public function testMemberJade(): void
    {
        $uid = $this->player();
        $this->assertSame(['status' => -1, 'jade' => 0], MemberJade::add(999999999, 5));
        $this->assertSame(['status' => 1, 'jade' => 0], MemberJade::add($uid, 0), '加 0 个不写库');
        $this->assertSame(['status' => 1, 'jade' => 30], MemberJade::add($uid, 30, 'countrymine', 'harvest', self::NOW));
        $this->assertSame(['status' => 1, 'jade' => 45], MemberJade::add($uid, 15));
        $this->assertSame(['status' => -1, 'jade' => 45], MemberJade::sub($uid, 46), '不足不扣');
        $this->assertSame(['status' => -2, 'jade' => 0], MemberJade::sub(999999999, 1));
        $this->assertSame(['status' => 1, 'jade' => 0], MemberJade::sub($uid, 45, 'shop', 'buy', self::NOW));
        $this->assertSame(0, MemberJade::get($uid));

        $logs = DB::table('jade_log')->where('uid', $uid)->orderBy('id')->get();
        $this->assertSame(
            [[1, 30, 30, 50, 3, 'countrymine', 'harvest', self::NOW], [1, 15, 45, 50, 3, '', ''], [2, 45, 0, 50, 3, 'shop', 'buy', self::NOW]],
            [
                $this->pick($logs[0], ['type', 'jade', 'balance', 'level', 'vip', 'class', 'method', 'dateline']),
                $this->pick($logs[1], ['type', 'jade', 'balance', 'level', 'vip', 'class', 'method']),
                $this->pick($logs[2], ['type', 'jade', 'balance', 'level', 'vip', 'class', 'method', 'dateline']),
            ]
        );
        $this->assertCount(3, $logs, '失败与 0 个不记流水');
    }

    public function testMemberStone(): void
    {
        $uid = $this->player();
        $this->assertSame(['status' => 1, 'stone' => 10], MemberStone::add($uid, 10));
        $this->assertSame(['status' => 1, 'stone' => 7], MemberStone::add($uid, 7, MemberStone::COLOR));
        $this->assertSame(['status' => 1, 'stone' => 3], MemberStone::add($uid, 3, MemberStone::BLOOD, 'heaven', 'action'));
        $this->assertSame(['status' => 0, 'stone' => 0], MemberStone::add($uid, 3, 4), '类型不对');
        $this->assertSame(['refinestone' => 10, 'colorstone' => 7, 'bloodstone' => 3], MemberStone::all($uid));
        $this->assertSame(['status' => -1, 'stone' => 7], MemberStone::sub($uid, 8, MemberStone::COLOR));
        $this->assertSame(['status' => 1, 'stone' => 2], MemberStone::sub($uid, 5, MemberStone::COLOR));
        $this->assertSame(['status' => -2, 'stone' => 0], MemberStone::sub(999999999, 1));
        $this->assertSame(2, MemberStone::get($uid, MemberStone::COLOR));

        $logs = array_map(fn (array $r): array => $this->pick($r, ['stone_type', 'type', 'stone', 'balance']), DB::table('stone_log')->where('uid', $uid)->orderBy('id')->get());
        $this->assertSame([[1, 1, 10, 10], [2, 1, 7, 7], [3, 1, 3, 3], [2, 2, 5, 2]], $logs);
    }

    public function testTraderLogJade(): void
    {
        $uid = $this->player();
        $this->assertSame(['status' => -1, 'type' => 3, 'number' => 0], TraderLog::subJade($uid, 3, 1), '没有记录');

        // 没有记录：插入一行，site 1、site1 开启、dateline 当前时间（旧版插入即记交易时间）
        $res = TraderLog::addJade($uid, 2, 5, now: self::NOW);
        $this->assertSame(['status' => 1, 'num' => 5, 'type' => 2, 'site1' => 1, 'site2' => 0, 'site3' => 0, 'site4' => 0, 'site5' => 0], $res);
        $row = TraderLog::get($uid);
        $this->assertSame([0, 5, 0, 1, 1, 0, self::NOW], [$row['get1'], $row['get2'], $row['get3'], $row['site'], $row['site1'], $row['site2'], $row['dateline']]);

        // 已有记录、$site 0：只加宝石，不动位置与时间
        $this->assertSame(['status' => 1, 'num' => 8, 'type' => 2], TraderLog::addJade($uid, 2, 3, now: self::NOW + 100));
        $this->assertSame(self::NOW, TraderLog::get($uid)['dateline']);

        // $site 3、$toSite 2、免费：开启 site3、关闭 site2、记交易时间
        DB::table('trader_log')->where('uid', $uid)->update(['site2' => 1]);
        $res = TraderLog::addJade($uid, 1, 1, 3, 2, 1, now: self::NOW + 200);
        $this->assertSame(['status' => 1, 'num' => 1, 'type' => 1, 'site1' => 1, 'site2' => 0, 'site3' => 1, 'site4' => 0, 'site5' => 0], $res);
        $row = TraderLog::get($uid);
        $this->assertSame([3, 0, 1, self::NOW + 200], [$row['site'], $row['site2'], $row['site3'], $row['dateline']]);
        $this->assertSame(['status' => 0, 'num' => 0, 'type' => 0], TraderLog::addJade($uid, 4, 1), '类型不对');

        $this->assertSame(['status' => -1, 'type' => 2, 'number' => 0], TraderLog::subJade($uid, 2, 9));
        $this->assertSame(['status' => 1, 'type' => 2, 'number' => 2, 'jade' => 2], TraderLog::subJade($uid, 2, 6, 'tavern', 'buy'));
        $this->assertSame(2, TraderLog::jade($uid, 2));

        $logs = array_map(fn (array $r): array => $this->pick($r, ['jadetype', 'type', 'jade', 'balance', 'level', 'vip']), DB::table('tavernjade_log')->where('uid', $uid)->orderBy('id')->get());
        $this->assertSame([[2, 1, 5, 5, 50, 3], [2, 1, 3, 8, 50, 3], [1, 1, 1, 1, 50, 3], [2, 2, 6, 2, 50, 3]], $logs);
    }

    public function testOwnGemAndChips(): void
    {
        $uid = $this->player();
        $this->assertSame(['status' => -1], OwnGem::addGem($uid, 999));
        // 旧版 add_gem 返回的 power 恒为模板 level0，入库的是 level{N}
        $this->assertSame(['status' => 1, 'name' => Gem::ROWS[1]['name'], 'type' => 1, 'power' => Gem::ROWS[1]['level0']], OwnGem::addGem($uid, 1, 2, 3, self::NOW));
        $rows = DB::table('own_gem')->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame([1, 1, 2, Gem::ROWS[1]['level2'], 0, 0, self::NOW], $this->pick($rows[0], ['gemid', 'type', 'level', 'power', 'eid', 'site', 'dateline']));
        [$a, $b, $c] = array_map('intval', array_column($rows, 'id'));

        $this->assertSame(1, OwnGem::adron($uid, $a, 777, 1));
        $this->assertSame(1, OwnGem::adron($uid, $b, 777, 2));
        $this->assertSame(0, OwnGem::adron($uid + 1, $c, 777, 3), '不是自己的宝石');
        $byEid = OwnGem::byEid($uid, 777);
        $this->assertSame([1, 2], array_keys($byEid));
        $this->assertSame(1, $byEid[1]['quality'], 'ceil(2 / 4)');
        $this->assertSame($b, (int)OwnGem::byEid($uid, 777, 2)['id']);
        $this->assertCount(2, OwnGem::byEid($uid, [777, 778]));
        $this->assertSame([1, 1, 1, 2, 6, 6], [OwnGem::quality(0), OwnGem::quality(1), OwnGem::quality(4), OwnGem::quality(5), OwnGem::quality(24), OwnGem::quality(25)]);

        $this->assertSame(2, OwnGem::packOffByEid($uid, 777));
        $this->assertSame([], OwnGem::byEid($uid, 777));
        $this->assertSame(1, OwnGem::delGem($uid, $c));
        $this->assertSame(0, OwnGem::delGem($uid, $c), '已删除');
        $this->assertNull(OwnGem::find($uid, $c));

        // 宝石碎片 member.gem + gem_log
        $this->assertSame(['status' => 1, 'gem' => 80], OwnGem::addChip($uid, 80, 'sanctum', 'action'));
        $this->assertSame(['status' => -1, 'gem' => 80], OwnGem::subChip($uid, 81));
        $this->assertSame(['status' => 1, 'gem' => 10], OwnGem::subChip($uid, 70));
        $this->assertSame(10, OwnGem::chip($uid));
        $logs = array_map(fn (array $r): array => $this->pick($r, ['type', 'gem', 'balance', 'class']), DB::table('gem_log')->where('uid', $uid)->orderBy('id')->get());
        $this->assertSame([[1, 80, 80, 'sanctum'], [2, 70, 10, '']], $logs);

        // 宝石格子占用：旧版 add_gemuse / sub_gemuse 的数量不大于 1 时按 1，减到 0 为止
        $this->assertSame(1, OwnGem::addGemuse($uid, 0));
        $this->assertSame(1, OwnGem::addGemuse($uid, 3));
        $this->assertSame(4, (int)DB::table('member')->where('uid', $uid)->value('gemuse'));
        $this->assertSame(1, OwnGem::subGemuse($uid, 10));
        $this->assertSame(0, (int)DB::table('member')->where('uid', $uid)->value('gemuse'));
        $this->assertSame(0, OwnGem::addGemuse(999999999));
    }

    public function testOwnSoul(): void
    {
        $uid = $this->player();
        $this->assertSame([], OwnSoul::addHeroSoul($uid, 999));
        $this->assertFalse(OwnSoul::isPackFull($uid));

        $one = OwnSoul::addHeroSoul($uid, 1, 2);
        $this->assertSame(['name' => Soul::ROWS[1]['name'], 'type' => 1, 'quality' => 1, 'image' => 11, 'level' => 2, 'power' => SoulType::ROWS[1]['lv2']], array_diff_key($one, ['id' => 0, 'num' => 0]));
        $this->assertSame(1, $one['num']);
        $this->assertSame(1, (int)DB::table('member')->where('uid', $uid)->value('souluse'));

        // 多个：批量插入，返回不带 id、带 uid；占用加 num
        $many = OwnSoul::addHeroSoul($uid, 60, 0, 3);
        $this->assertSame([$uid, 3, 106, SoulType::ROWS[10]['lv0'] . '%'], [$many['uid'], $many['num'], $many['image'], $many['power']]);
        $this->assertSame(SoulType::ROWS[10]['lv0'], (int)DB::table('own_soul')->where('uid', $uid)->where('type', 10)->value('power'), '库里存整数');
        $this->assertSame(4, (int)DB::table('member')->where('uid', $uid)->value('souluse'));
        $this->assertSame(4, OwnSoul::unequipCount($uid));
        $this->assertSame(3, OwnSoul::unequipCount($uid, 6));
        $this->assertTrue(OwnSoul::isPackFull($uid), 'soulpack 4 <= souluse 4（旧版 tower _isfull）');

        // 超过品质等级上限取不到属性
        $this->assertSame(0, OwnSoul::power(8, 1, 1));
        $this->assertSame(SoulType::ROWS[10]['lv20'], OwnSoul::power(20, 10, 6));

        $this->assertSame(1, OwnSoul::addSoulpack($uid, 2));
        $this->assertFalse(OwnSoul::isPackFull($uid));
        $this->assertSame(1, OwnSoul::delSoul($uid, (int)$one['id']));
        $this->assertSame(1, OwnSoul::subSouluse($uid));
        $this->assertSame(1, OwnSoul::subSouluse($uid, 10));
        $this->assertSame(0, (int)DB::table('member')->where('uid', $uid)->value('souluse'), '减到 0 为止');
        $this->assertSame(3, OwnSoul::unequipCount($uid));
    }

    public function testOwnMountAndChips(): void
    {
        $uid = $this->player();
        $this->assertSame(['status' => -1], OwnMount::addMount($uid, 999));

        // 整匹：0 级，属性 = 初始 + 成长 × 0 级系数
        $res = OwnMount::addMount($uid, 1, now: self::NOW);
        $temp = MountTemp::ROWS[1];
        $coef = MountLevel::ROWS[0]['coefficient'];
        $this->assertSame(1, $res['status']);
        $row = DB::table('own_mount')->where('uid', $uid)->first();
        $this->assertSame(
            [1, 0, 0, MountLevel::ROWS[0]['exp'], $temp['attack'] + (int)($temp['attack_up'] * $coef), $temp['hp'] + (int)($temp['hp_up'] * $coef), $temp['exp'], 0, self::NOW],
            $this->pick($row, ['mid', 'gid', 'level', 'levelup_exp', 'attack', 'hp', 'toexp', 'chip', 'dateline'])
        );

        // 碎片：3 片一行；再 7 片恰好 10 片，碎片行本身变成坐骑
        $this->assertSame(1, OwnMount::addMount($uid, 2, 1, 3, 'herothrone', 'reward')['status']);
        $this->assertSame(3, (int)OwnMount::infoById($uid, 2, 1)['chip']);
        $this->assertSame(1, OwnMount::addMount($uid, 2, 1, 7)['status']);
        $this->assertSame([], OwnMount::infoById($uid, 2, 1));
        $this->assertSame(1, DB::table('own_mount')->where('uid', $uid)->where('mid', 2)->whereNull('deleted_at')->count());

        // 没有碎片行时 25 片：2 匹 + 5 片
        OwnMount::addMount($uid, 3, 1, 25);
        $this->assertSame(2, DB::table('own_mount')->where('uid', $uid)->where('mid', 3)->where('chip', 0)->count());
        $this->assertSame(5, (int)OwnMount::infoById($uid, 3, 1)['chip']);
        // 已有 5 片再加 15 片：超过 10 片，另插 2 匹，余数 0 的碎片行也变成坐骑（旧版如此，共多出 1 匹）
        OwnMount::addMount($uid, 3, 1, 15);
        $this->assertSame(5, DB::table('own_mount')->where('uid', $uid)->where('mid', 3)->where('chip', 0)->count());

        $logs = array_map(fn (array $r): array => $this->pick($r, ['mid', 'num', 'blance', 'class']), DB::table('mountchip_log')->where('uid', $uid)->orderBy('id')->get());
        $this->assertSame([[2, 3, 3, 'herothrone'], [2, 7, 0, ''], [3, 25, 5, ''], [3, 15, 0, '']], $logs);

        // 乘骑 / 卸下全部 / 删除
        $mountId = (int)$row['id'];
        $this->assertSame(1, OwnMount::adorn($uid, $mountId, 555));
        $this->assertCount(1, OwnMount::byGid($uid, 555));
        OwnMount::takeDownAll($uid, 555);
        $this->assertSame([], OwnMount::byGid($uid, 555));
        $this->assertSame(1, OwnMount::disable($uid, [$mountId], 'mount', 'levelup', self::NOW));
        $this->assertSame([], OwnMount::infoById($uid, $mountId));
        $this->assertSame(0, OwnMount::disable($uid, $mountId), '已删除');
        $log = DB::table('mount_info_log')->where('uid', $uid)->first();
        $this->assertSame([2, 1, $temp['name'], 0, 1, 50, 3, 'mount'], $this->pick($log, ['type', 'mid', 'mname', 'mlevel', 'quality', 'level', 'vip', 'class']));
    }

    public function testEquipmentChips(): void
    {
        $uid = $this->player();
        $this->assertSame(['status' => -1], Equipment::addChip($uid, 9999));

        // 品质 2：2 片一件。1 片一行，再 1 片恰好满，碎片行变成整件
        $tpl = Equip::ROWS[3];
        $this->assertSame(2, $tpl['quality']);
        $this->assertSame(['status' => 1], Equipment::addChip($uid, 3, 1, 'copies', 'reward'));
        $chipRow = DB::table('equipment')->where('uid', $uid)->where('equipid', 3)->first();
        $this->assertSame([1, $tpl['type'], $tpl['power'], $tpl['power']], $this->pick($chipRow, ['chip', 'type', 'power', 'powerup']));
        $this->assertSame(['status' => 1], Equipment::addChip($uid, 3, 1));
        $this->assertSame(0, (int)DB::table('equipment')->where('id', (int)$chipRow['id'])->value('chip'));

        // 品质 4：5 片一件。没有碎片行时 12 片：2 件 + 2 片；再 4 片（共 6 片）：另插 1 件，碎片行留 1 片
        $this->assertSame(4, Equip::ROWS[19]['quality']);
        Equipment::addChip($uid, 19, 12);
        $this->assertSame(2, DB::table('equipment')->where('uid', $uid)->where('equipid', 19)->where('chip', 0)->count());
        Equipment::addChip($uid, 19, 4);
        $this->assertSame(3, DB::table('equipment')->where('uid', $uid)->where('equipid', 19)->where('chip', 0)->count());
        $this->assertSame(1, (int)DB::table('equipment')->where('uid', $uid)->where('equipid', 19)->where('chip', '>', 0)->value('chip'));

        $logs = array_map(fn (array $r): array => $this->pick($r, ['itemid', 'itemtype', 'quality', 'num', 'blance', 'class']), DB::table('itemchip_log')->where('uid', $uid)->orderBy('id')->get());
        $this->assertSame([[3, 1, 2, 1, 1, 'copies'], [3, 1, 2, 1, 0, ''], [19, 1, 4, 12, 2, ''], [19, 1, 4, 4, 1, '']], $logs);
    }

    public function testTalentScrolls(): void
    {
        $uid = $this->player();
        $this->assertSame(['status' => -1], Equipment::addTalentScroll($uid, 999));
        $this->assertSame(['status' => -1], Equipment::subTalentScroll($uid, 1, 1), '没有卷轴');

        // 卷轴：同类型同品级叠在 number 上；info.id 照旧版为卷轴类型
        $res = Equipment::addTalentScroll($uid, 1, 0, 2, 'elite', 'action');
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['info']['id']);
        $this->assertSame('物攻天赋卷轴', $res['info']['name']);
        $this->assertSame(1, Equipment::addTalentScroll($uid, 1, 0, 3)['status']);
        $scroll = Equipment::talentScroll($uid, 1, 1);
        $this->assertSame([6, 1, 5, 0, TalentScroll::ROWS[1]['price'], 1], $this->pick($scroll, ['type', 'quality', 'number', 'chip', 'saleprice', 'equipid']));

        // 卷轴碎片（品质 1：2 片一卷）：1 片一行；再 3 片 → 合成 2 卷，碎片行删除
        $this->assertSame(1, Equipment::addTalentScroll($uid, 1, 1, 1)['status']);
        $this->assertSame(1, (int)Equipment::talentScroll($uid, 1, 1, 1)['chip']);
        $merged = Equipment::addTalentScroll($uid, 1, 1, 3);
        $this->assertSame(1, $merged['status']);
        $this->assertNull(Equipment::talentScroll($uid, 1, 1, 1));
        $this->assertSame(7, (int)Equipment::talentScroll($uid, 1, 1)['number']);

        // 消耗：不够 -2，扣完软删除
        $this->assertSame(['status' => -2], Equipment::subTalentScroll($uid, 1, 1, 8));
        $this->assertSame(['status' => 1], Equipment::subTalentScroll($uid, 1, 1, 3, 'talent', 'study'));
        $this->assertSame(['status' => 1], Equipment::subTalentScroll($uid, 1, 1, 4));
        $this->assertNull(Equipment::talentScroll($uid, 1, 1));
        $this->assertNotNull(DB::table('equipment')->where('id', (int)$scroll['id'])->value('deleted_at'));

        $logs = array_map(fn (array $r): array => $this->pick($r, ['type', 'scrolltype', 'quality', 'scroll', 'balance', 'level', 'vip']), DB::table('talent_scroll_log')->where('uid', $uid)->orderBy('id')->get());
        $this->assertSame([[1, 1, 1, 2, 2, 50, 3], [1, 1, 1, 3, 5, 50, 3], [1, 1, 1, 2, 7, 50, 3], [2, 1, 1, 3, 4, 50, 3], [2, 1, 1, 4, 0, 50, 3]], $logs);
        // 碎片流水：合成那次的碎片行删除、剩余 0；旧版累加未满时也记剩余 0
        $chipLogs = array_map(fn (array $r): array => $this->pick($r, ['itemid', 'itemtype', 'num', 'blance', 'itemname']), DB::table('itemchip_log')->where('uid', $uid)->orderBy('id')->get());
        $this->assertSame([[1, 6, 1, 1, '物攻天赋卷轴'], [1, 6, 3, 0, '物攻天赋卷轴']], $chipLogs);
    }

    public function testEquipEssence(): void
    {
        $uid = $this->player();
        $this->assertSame(0, Equipment::addTowerItem($uid, 999, 1));
        $this->assertSame(1, Equipment::addTowerItem($uid, 4, 3));
        $this->assertSame(1, Equipment::addTowerItem($uid, 4, 2));
        $item = Equipment::towerItem($uid, 4);
        $this->assertSame([104, 100, EquipEssence::ROWS[4]['quality'] + 100, 5, EquipEssence::ROWS[4]['price']], $this->pick($item, ['equipid', 'type', 'quality', 'number', 'saleprice']));
        $this->assertNull(DB::table('equipment')->where('id', (int)$item['id'])->value('site'), '精华不可装配');
        $this->assertCount(1, Equipment::towerItem($uid, [4, 5], 2));

        $this->assertSame(0, Equipment::subTowerItem($uid, 4, 6), '不足');
        $this->assertSame(1, Equipment::subTowerItem($uid, 4, 5));
        $this->assertSame(0, (int)Equipment::towerItem($uid, 4)['number'], '扣完行保留');
        $this->assertSame(1, Equipment::updateTowerItem($uid, 4, 9));
        $this->assertSame(9, (int)Equipment::towerItem($uid, 4)['number']);
    }

    public function testCountryDebris(): void
    {
        $uid = $this->player();
        $this->assertSame(0, CountryDebris::add($uid, 5), '没有国家记录');
        DB::table('member_country')->insert(['uid' => $uid, 'country' => 1]);
        $this->assertSame(5, CountryDebris::add($uid, 5, 'country', 'boss'));
        $this->assertSame(2, CountryDebris::sub($uid, 3));
        $this->assertSame(0, CountryDebris::sub($uid, 10), '不足扣到 0');
        $this->assertSame(0, CountryDebris::get($uid));
        $logs = array_map(fn (array $r): array => $this->pick($r, ['type', 'add_reduce', 'debris', 'class']), DB::table('debris_log')->where('uid', $uid)->orderBy('id')->get());
        $this->assertSame([[1, 5, 5, 'country'], [2, 3, 2, ''], [2, 10, 0, '']], $logs);
    }

    /**
     * 多个进程同时给同一玩家加减物品：余额、合成结果与流水条数都不丢不超
     */
    public function testConcurrentWorkers(): void
    {
        $uid = $this->player();
        $run = function (string $op, int $procs, int $times) use ($uid): int {
            $workers = [];
            for ($i = 0; $i < $procs; $i++) {
                $cmd = [PHP_BINARY, dirname(__DIR__) . '/Support/storage_worker.php', $op, (string)$uid, (string)$times];
                $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($proc);
                $workers[] = [$proc, $pipes];
            }
            $ok = 0;
            foreach ($workers as [$proc, $pipes]) {
                $out = trim((string)stream_get_contents($pipes[1]));
                $err = trim((string)stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($proc), $err);
                // 输出最后一行是成功次数（前面可能有 Redis 连接日志）
                $lines = preg_split('/\R/', $out) ?: [''];
                $ok += (int)end($lines);
            }
            return $ok;
        };

        // 玉石：4 × 30 次加 1，再 4 × 40 次扣 1（只够 120 次）
        $this->assertSame(120, $run('jade_add', 4, 30));
        $this->assertSame(120, MemberJade::get($uid));
        $this->assertSame(120, $run('jade_sub', 4, 40));
        $this->assertSame(0, MemberJade::get($uid));
        $this->assertSame(240, DB::table('jade_log')->where('uid', $uid)->count());

        // 酒馆紫宝石：首行插入并发也只有一行
        $this->assertSame(100, $run('trader_add', 4, 25));
        $this->assertSame(1, DB::table('trader_log')->where('uid', $uid)->count());
        $this->assertSame(100, TraderLog::jade($uid, 3));

        // 装备碎片（2 片一件）：80 片 → 40 件，不留碎片行
        $this->assertSame(80, $run('chip_add', 4, 20));
        $this->assertSame(40, DB::table('equipment')->where('uid', $uid)->where('equipid', 3)->where('chip', 0)->whereNull('deleted_at')->count());
        $this->assertSame(0, DB::table('equipment')->where('uid', $uid)->where('equipid', 3)->where('chip', '>', 0)->count());

        // 坐骑碎片（10 片一匹）：40 片 → 4 匹
        $this->assertSame(40, $run('mount_chip_add', 4, 10));
        $this->assertSame(4, DB::table('own_mount')->where('uid', $uid)->where('mid', 1)->where('chip', 0)->count());
        $this->assertSame(0, DB::table('own_mount')->where('uid', $uid)->where('mid', 1)->where('chip', '>', 0)->count());

        // 卷轴碎片（2 片一卷）：40 片 → 20 卷，同一行
        $this->assertSame(40, $run('scroll_chip_add', 4, 10));
        $this->assertSame(20, (int)Equipment::talentScroll($uid, 1, 1)['number']);
        $this->assertSame(1, DB::table('equipment')->where('uid', $uid)->where('type', 6)->whereNull('deleted_at')->count());

        // 装备精华：30 个，4 × 10 次扣 1，只成功 30 次
        Equipment::addTowerItem($uid, 1, 30);
        $this->assertSame(30, $run('essence_sub', 4, 10));
        $this->assertSame(0, (int)Equipment::towerItem($uid, 1)['number']);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     * @return list<int|string>
     */
    private function pick(array $row, array $keys): array
    {
        return array_map(static fn (string $k): int|string => is_numeric($row[$k]) ? (int)$row[$k] : (string)$row[$k], $keys);
    }
}
