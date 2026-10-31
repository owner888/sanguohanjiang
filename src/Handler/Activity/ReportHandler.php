<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Cross\UcModel;
use Hanjiang\Handler\Pvp\GodHandler;
use Hanjiang\Handler\Pvp\GvgHandler;
use Hanjiang\Handler\Pvp\WorldarenaHandler;
use Hanjiang\MasterData\CountryCityEffect;
use Hanjiang\Model\OpenMap;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 分享战报（移植旧版 controllers/report.php:save）
 *
 * 客户端（miscpop.js）调用 save&id=&type=：先按类型取战报（arena / challenge / countrymine / mine 为信件战报 pm_report，
 * map 为推图首杀战报 mission_report，siege 为国战战报 country_city_report，god 为战神殿战报（id 为「场次_第几局」），
 * worldarena 为跨服演武战报，gvg 为群服争霸战报（id 为「轮次_场次」）），取不到回 -1；再把战报存到 report 服务器换分享链接（transfer_model->save_report →
 * Cross\UcModel::saveReport，单服下在本进程内），失败回 -2。没有配置展示页地址（config app.report_url）时按分享失败回 -2。
 * 不移植：c=report&m=send（客户端发到外部错误收集地址 error.zhanchengkeji.com，不是游戏服接口）。
 */
final class ReportHandler
{
    /**
     * 保存战报: c=report&m=save&id=&type=
     */
    public static function save(Context $ctx): array
    {
        $return = ['status' => 0];
        $id = (string)$ctx->input('id', '');
        $type = (string)$ctx->input('type', '');
        $report = self::report($id, $type);
        if ($report === null) {
            // 获取战报失败
            $return['status'] = -1;
            return $return;
        }
        $save = UcModel::saveReport($type, self::normalizeId($id), $report);
        if ($save['status'] !== 1) {
            // 分享战报失败
            $return['status'] = -2;
            return $return;
        }
        $return['status'] = 1;
        $return['link'] = $save['link'];
        return $return;
    }

    /**
     * 根据 id 获取战报（_get_report）；取不到为 null
     *
     * @return array<string, mixed>|null
     */
    private static function report(string $id, string $type): ?array
    {
        $report = match ($type) {
            'arena', 'challenge', 'countrymine', 'mine' => MessageHandler::reportDetail((int)$id),
            'god' => self::godReport($id),
            'map' => self::mapReport((int)$id),
            'siege' => self::siegeReport((int)$id),
            'worldarena' => WorldarenaHandler::reportInfo((int)$id),
            'gvg' => self::gvgReport($id),
            default => null,
        };
        return $report !== null && ($report['status'] ?? 0) === 1 ? $report : null;
    }

    /**
     * 战神殿战报（id 为「场次_第几局」）
     *
     * @return array<string, mixed>|null
     */
    private static function godReport(string $id): ?array
    {
        $idArr = explode('_', $id);
        return GodHandler::reportInfo((int)$idArr[0], (int)($idArr[1] ?? 0));
    }

    /**
     * 群服争霸战报（_gvg_report：id 为「轮次_场次」）
     *
     * @return array<string, mixed>|null
     */
    private static function gvgReport(string $id): ?array
    {
        $idArr = explode('_', $id);
        return GvgHandler::shareReport((int)$idArr[0], (int)($idArr[1] ?? 0));
    }

    /**
     * 推图首杀战报（_map_report）
     *
     * @return array<string, mixed>|null
     */
    private static function mapReport(int $id): ?array
    {
        $result = OpenMap::missionReportById($id);
        if ($result['status'] !== 1) {
            return null;
        }
        $return = ['status' => 1];
        $return['battle'] = $result['report'];
        $return['info']['background'] = $result['info']['background'];
        $return['battle']['left_role'] = ['name' => $result['info']['nickname'], 'level' => $result['info']['userlevel']];
        $return['battle']['right_role'] = ['name' => $result['info']['name'], 'level' => $result['info']['level']];
        $return['info']['win'] = $result['report']['assess'] ?? null;
        return $return;
    }

    /**
     * 国战战报（_siege_report：country_city_report 的一行，双方名单重排、带城池名）
     *
     * @return array<string, mixed>|null
     */
    private static function siegeReport(int $id): ?array
    {
        $row = DB::table('country_city_report')->select('city', 'report')->where('id', $id)->whereNull('deleted_at')->first();
        if ($row === null) {
            // 战报不存在
            return null;
        }
        $data = json_decode((string)$row['report'], true);
        if (!is_array($data)) {
            return null;
        }
        $attack = array_values((array)($data['attack'] ?? []));
        $defense = array_values((array)($data['defense'] ?? []));
        $return = ['status' => 1];
        $return['battle'] = $data['battle'] ?? null;
        $return['attack'] = $attack;
        $return['defense'] = $defense;
        $return['city_name'] = (string)(CountryCityEffect::ROWS[(int)$row['city']]['name'] ?? '');
        $return['attack_name'] = $data['attack_name'] ?? null;
        $return['attack_level'] = $data['attack_level'] ?? null;
        $return['defense_name'] = $data['defense_name'] ?? null;
        $return['defense_level'] = $data['defense_level'] ?? null;
        $return['cid'] = $data['cid'] ?? null;
        $return['challengercid'] = $data['challengercid'] ?? null;
        $return['npc'] = $data['npc'] ?? 1;
        $return['info']['win'] = $data['battle']['win'] ?? null;
        $return['attack_num'] = count($attack);
        $return['defense_num'] = count($defense);
        return $return;
    }

    /**
     * 分享用的战报编号：旧版 save_report 一律 intval（战神殿「场次_第几局」只剩场次，同一场三局会撞号），这里战神殿保留「场次_第几局」
     */
    private static function normalizeId(string $id): string
    {
        return preg_match('/^\d+_\d+$/', $id) === 1 ? $id : (string)(int)$id;
    }
}
