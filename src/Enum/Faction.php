<?php

declare(strict_types=1);

namespace Hanjiang\Enum;

/**
 * 战役大地图势力定义 (1~16)
 */
enum Faction: int
{
    case YellowTurban = 1;  // 黄巾起义
    case YuanShao = 2;      // 袁绍势力
    case GongsunZan = 3;    // 公孙瓒势力
    case DongZhuo = 4;      // 董卓势力
    case MaTeng = 5;        // 马腾势力
    case LiuBiao = 6;       // 刘表势力
    case YuanShu = 7;       // 袁术势力
    case LuBu = 8;          // 吕布势力
    case CaoCao = 9;        // 曹操势力
    case LiuBei = 10;       // 刘备势力
    case SunShi = 11;       // 孙氏势力
    case NanMan = 12;       // 南蛮势力
    case XiongNu = 13;      // 匈奴势力
    case Heroes = 14;       // 英雄集结
    case SongDynasty = 15;  // 宋朝势力
    case SuiTang = 16;      // 隋唐势力

    public function label(): string
    {
        return match ($this) {
            self::YellowTurban => '黄巾起义',
            self::YuanShao => '袁绍势力',
            self::GongsunZan => '公孙瓒势力',
            self::DongZhuo => '董卓势力',
            self::MaTeng => '马腾势力',
            self::LiuBiao => '刘表势力',
            self::YuanShu => '袁术势力',
            self::LuBu => '吕布势力',
            self::CaoCao => '曹操势力',
            self::LiuBei => '刘备势力',
            self::SunShi => '孙氏势力',
            self::NanMan => '南蛮势力',
            self::XiongNu => '匈奴势力',
            self::Heroes => '英雄集结',
            self::SongDynasty => '宋朝势力',
            self::SuiTang => '隋唐势力',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::YellowTurban => 'yellow_turban',
            self::YuanShao => 'yuan_shao',
            self::GongsunZan => 'gongsun_zan',
            self::DongZhuo => 'dong_zhuo',
            self::MaTeng => 'ma_teng',
            self::LiuBiao => 'liu_biao',
            self::YuanShu => 'yuan_shu',
            self::LuBu => 'lu_bu',
            self::CaoCao => 'cao_cao',
            self::LiuBei => 'liu_bei',
            self::SunShi => 'sun_shi',
            self::NanMan => 'nan_man',
            self::XiongNu => 'xiong_nu',
            self::Heroes => 'heroes',
            self::SongDynasty => 'song_dynasty',
            self::SuiTang => 'sui_tang',
        };
    }
}
