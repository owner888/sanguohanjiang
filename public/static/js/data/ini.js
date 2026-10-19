var battle_pos = {};
var hero_ini = {}; (function() {

    battle_pos['left'] = { //左边位置坐标
        //my
        1 : {
            x: 360,
            y: 300
        },
        2 : {
            x: 330,
            y: 400
        },
        3 : {
            x: 300,
            y: 500
        },
        4 : {
            x: 250,
            y: 300
        },
        5 : {
            x: 220,
            y: 400
        },
        6 : {
            x: 190,
            y: 500
        },
        7 : {
            x: 140,
            y: 300
        },
        8 : {
            x: 110,
            y: 400
        },
        9 : {
            x: 80,
            y: 500
        },
    };
    battle_pos['right'] = { //右边位置坐标
        //pk
        1 : {
            x: 580,
            y: 300
        },
        2 : {
            x: 610,
            y: 400
        },
        3 : {
            x: 640,
            y: 500
        },
        4 : {
            x: 690,
            y: 300
        },
        5 : {
            x: 720,
            y: 400
        },
        6 : {
            x: 750,
            y: 500
        },
        7 : {
            x: 800,
            y: 300
        },
        8 : {
            x: 830,
            y: 400
        },
        9 : {
            x: 860,
            y: 500
        },
    };

    var normal_s = 90;
    var normal_a = 80;
    var normal_a1 = 72;
    var normal_a2 = 60;
    var normal_sf = 600;
    var normal_at = 500;

    hero_ini = {
        worldboss1: {
            id: "worldboss1",
            herotype: 1,
            width: 525,
            height: 262,
            mx: -190,
            my: 0,
            attackt: normal_a * 4,
            //攻击总共时间
            rx: -124,
            //翻转的偏移量
            sx: 152,
            sy: 0,
            //影子 44
            bx: 50,
            by: 0,
            //条容器(by基本用不到)
            lx: 132,
            ly: 128,
            animations2: [ //攻击动画
            {
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [525, 0],
                interval: normal_a
            },
            {
                rect: [1050, 0],
                interval: normal_a
            },
            {
                rect: [0, 262],
                interval: normal_a
            },
            {
                rect: [525, 262],
                interval: normal_a
            },
            {
                rect: [1050, 262],
                interval: normal_a
            },
            {
                rect: [0, 524],
                interval: normal_a
            },
            {
                rect: [525, 524],
                interval: normal_a
            },
            ]
        },
        countryboss: {
            id: "countryboss",
            herotype: 1,
            width: 525,
            height: 262,
            mx: -190,
            my: 0,
            attackt: normal_a * 4,
            //攻击总共时间
            rx: -124,
            //翻转的偏移量
            sx: 152,
            sy: 0,
            //影子 44
            bx: 50,
            by: 0,
            //条容器(by基本用不到)
            lx: 132,
            ly: 128,
            animations2: [ //攻击动画
            {
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [525, 0],
                interval: normal_a
            },
            {
                rect: [1050, 0],
                interval: normal_a
            },
            {
                rect: [0, 262],
                interval: normal_a
            },
            {
                rect: [525, 262],
                interval: normal_a
            },
            {
                rect: [1050, 262],
                interval: normal_a
            },
            ]
        },
        newyearboss : {
            id : "newyearboss",
            herotype : 2,
            width : 525,
            height : 262,
            //攻击总共时间
            attackt : normal_a * 8,
            mx: 180,
            my: -45,
            rx: -504,
            sx: 140,
            sy: 0,
            bx: 38,
            by: 0,
            lx: 120,
            ly: 128,
            animations2 : [//攻击动画
            {
                rect: [0, 0],
                interval: normal_a
            },
            {
                rect: [525, 0],
                interval: normal_a
            },
            {
                rect: [1050, 0],
                interval: normal_a
            },
            {
            	rect: [1575, 0],
            	interval: normal_a
            },
            {
            	rect: [2100, 0],
            	interval: normal_a
            }, 
            {
                rect: [0, 262],
                interval: normal_a
            },
            {
                rect: [525, 262],
                interval: normal_a
            },
            {
                rect: [1050, 262],
                interval: normal_a
            }]
        },
        1 : {
            id: 1,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -112,
            my: 0,
            attackt: normal_a * 4,
            //攻击总共时间
            rx: -90,
            //翻转的偏移量
            sx: 62,
            sy: 0,
            //影子 44
            bx: 80,
            by: 0,
            //条容器(by基本用不到)
            lx: 132,
            ly: 128,
            //闪避暴击 lx:37, ly:92,//闪避暴击
            px: 40,
            //power x
            animations: [ //站立动画
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [ //攻击动画
            {
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            ]
        },
        2 : {
            id: 2,
            herotype: 2,
            width: 182,
            height: 160,
            mx: -40,
            my: 0,
            attackt: normal_a * 6,
            //攻击总时间
            rx: -234,
            sx: -8,
            sy: 0,
            bx: 8,
            by: 0,
            lx: 50,
            ly: 132,
            px: -34,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [182, 0],
                interval: normal_s
            },
            {
                rect: [364, 0],
                interval: normal_s
            },
            {
                rect: [546, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [182, 0],
                interval: normal_a
            },
            {
                rect: [364, 0],
                interval: normal_a
            },
            {
                rect: [546, 0],
                interval: normal_a
            },
            {
                rect: [728, 0],
                interval: normal_a
            },
            {
                rect: [0, 160],
                interval: normal_a
            },
            ]
        },
        3 : {
            id: 3,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -70,
            my: 0,
            attackt: normal_a1 * 10,
            //攻击总时间
            rx: -172,
            sx: 22,
            sy: 0,
            bx: 38,
            by: 0,
            lx: 91,
            ly: 128,
            px: 0,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [260, 0],
                interval: normal_a2
            },
            {
                rect: [520, 0],
                interval: normal_a2
            },
            {
                rect: [780, 0],
                interval: normal_a2
            },
            {
                rect: [1040, 0],
                interval: normal_a2
            },
            {
                rect: [0, 210],
                interval: normal_a2
            },
            {
                rect: [260, 210],
                interval: normal_a2
            },
            {
                rect: [520, 210],
                interval: normal_a2
            },
            {
                rect: [780, 210],
                interval: normal_a2
            },
            {
                rect: [1040, 210],
                interval: normal_a2
            },
            ]
        },
        4 : {
            id: 4,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -106,
            my: 0,
            attackt: normal_a1 * 4 + normal_a * 3,
            rx: -102,
            sx: 54,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 127,
            ly: 128,
            px: 32,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            {
                rect: [0, 210],
                interval: normal_a
            },
            {
                rect: [260, 210],
                interval: normal_a
            },
            ]
        },
        5 : {
            id: 5,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -80,
            my: 0,
            attackt: normal_a * 5,
            rx: -156,
            sx: 30,
            sy: 0,
            bx: 46,
            by: 0,
            lx: 92,
            ly: 128,
            px: 0,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        6 : {
            id: 6,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -86,
            my: 0,
            attackt: normal_a * 5,
            rx: -140,
            sx: 36,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 105,
            ly: 128,
            px: 10,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        7 : {
            id: 7,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -76,
            my: 0,
            attackt: normal_a * 5,
            rx: -156,
            sx: 28,
            sy: 0,
            bx: 45,
            by: 0,
            lx: 97,
            ly: 128,
            px: 0,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        8 : {
            id: 8,
            herotype: 1,
            width: 301,
            height: 210,
            mx: -128,
            my: 0,
            attackt: normal_a * 4,
            rx: -58,
            sx: 78,
            sy: 0,
            bx: 97,
            by: 0,
            lx: 150,
            ly: 128,
            px: 50,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [301, 0],
                interval: normal_s
            },
            {
                rect: [602, 0],
                interval: normal_s
            },
            {
                rect: [903, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [301, 0],
                interval: normal_a
            },
            {
                rect: [602, 0],
                interval: normal_a
            },
            {
                rect: [903, 0],
                interval: normal_a
            },
            ]
        },
        9 : {
            id: 9,
            herotype: 1,
            width: 301,
            height: 210,
            mx: -140,
            my: 0,
            attackt: normal_a2 * 9,
            rx: -36,
            sx: 92,
            sy: 0,
            bx: 105,
            by: 0,
            lx: 156,
            ly: 128,
            px: 60,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [301, 0],
                interval: normal_s
            },
            {
                rect: [602, 0],
                interval: normal_s
            },
            {
                rect: [903, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [301, 0],
                interval: normal_a2
            },
            {
                rect: [602, 0],
                interval: normal_a2
            },
            {
                rect: [903, 0],
                interval: normal_a2
            },
            {
                rect: [1204, 0],
                interval: normal_a2
            },
            {
                rect: [0, 210],
                interval: normal_a2
            },
            {
                rect: [301, 210],
                interval: normal_a2
            },
            {
                rect: [602, 210],
                interval: normal_a2
            },
            {
                rect: [903, 210],
                interval: normal_a2
            },
            ]
        },
        10 : {
            id: 10,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -126,
            my: 0,
            attackt: normal_a1 * 8,
            //攻击总时间
            rx: -66,
            sx: 76,
            sy: 0,
            bx: 90,
            by: 0,
            lx: 141,
            ly: 128,
            px: 50,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            {
                rect: [260, 210],
                interval: normal_a1
            },
            {
                rect: [520, 210],
                interval: normal_a1
            },
            ]
        },
        11 : {
            id: 11,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -114,
            my: 0,
            attackt: normal_a * 5,
            rx: -88,
            sx: 62,
            sy: 0,
            bx: 80,
            by: 0,
            lx: 133,
            ly: 126,
            px: 42,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        12 : {
            id: 12,
            herotype: 1,
            width: 301,
            height: 210,
            mx: -135,
            my: 0,
            attackt: normal_a1 * 4,
            rx: -48,
            sx: 83,
            sy: 0,
            bx: 98,
            by: 0,
            lx: 150,
            ly: 126,
            px: 63,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [301, 0],
                interval: normal_s
            },
            {
                rect: [602, 0],
                interval: normal_s
            },
            {
                rect: [903, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [301, 0],
                interval: normal_a1
            },
            {
                rect: [602, 0],
                interval: normal_a1
            },
            {
                rect: [903, 0],
                interval: normal_a1
            },
            ]
        },
        13 : {
            id: 13,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -88,
            my: 0,
            attackt: normal_a1 * 7 + 140,
            rx: -140,
            sx: 42,
            sy: 0,
            bx: 53,
            by: 0,
            lx: 105,
            ly: 126,
            px: 14,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            {
                rect: [260, 210],
                interval: normal_a1
            },
            {
                rect: [520, 210],
                interval: 140
            },
            ]
        },
        14 : {
            id: 14,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -113,
            my: 0,
            attackt: normal_a * 5,
            rx: -90,
            sx: 66,
            sy: 0,
            bx: 77,
            by: 0,
            lx: 128,
            ly: 126,
            px: 42,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        //无法整除，太大
        15 : {
            id: 15,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -122,
            my: 0,
            attackt: normal_a2 * 7,
            rx: -70,
            sx: 72,
            sy: 0,
            bx: 88,
            by: 0,
            lx: 140,
            ly: 126,
            px: 48,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [315, 0],
                interval: normal_a2
            },
            {
                rect: [630, 0],
                interval: normal_a2
            },
            {
                rect: [945, 0],
                interval: normal_a2
            },
            {
                rect: [1260, 0],
                interval: normal_a2
            },
            {
                rect: [0, 253],
                interval: normal_a2
            },
            {
                rect: [315, 253],
                interval: normal_a2
            },
            ]
        },
        16 : {
            id: 16,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -110,
            my: 0,
            attackt: normal_a * 7,
            rx: -94,
            sx: 62,
            sy: 0,
            bx: 75,
            by: 0,
            lx: 128,
            ly: 126,
            px: 38,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            {
                rect: [0, 210],
                interval: normal_a
            },
            {
                rect: [260, 210],
                interval: normal_a
            },
            ],
        },
        17 : {
            id: 17,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -84,
            my: 0,
            attackt: normal_a1 * 9,
            rx: -148,
            sx: 33,
            sy: 0,
            bx: 50,
            by: 0,
            lx: 102,
            ly: 126,
            px: 10,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            {
                rect: [260, 210],
                interval: normal_a1
            },
            {
                rect: [520, 210],
                interval: normal_a1
            },
            {
                rect: [780, 210],
                interval: normal_a1
            },
            ]
        },
        18 : {
            id: 18,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -110,
            my: 0,
            attackt: normal_a * 4,
            rx: -94,
            sx: 62,
            sy: 0,
            bx: 75,
            by: 0,
            lx: 128,
            ly: 126,
            px: 42,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            ]
        },
        19 : {
            id: 19,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -90,
            my: 0,
            attackt: normal_a1 * 6,
            rx: -136,
            sx: 38,
            sy: 0,
            bx: 55,
            by: 0,
            lx: 108,
            ly: 126,
            px: 16,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            ]
        },
        20 : {
            id: 20,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -110,
            my: 0,
            attackt: normal_a2 * 10 + 160,
            rx: -96,
            sx: 62,
            sy: 0,
            bx: 75,
            by: 0,
            lx: 128,
            ly: 126,
            px: 36,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [260, 0],
                interval: normal_a2
            },
            {
                rect: [520, 0],
                interval: normal_a2
            },
            {
                rect: [780, 0],
                interval: normal_a2
            },
            {
                rect: [1040, 0],
                interval: normal_a2
            },
            {
                rect: [0, 210],
                interval: normal_a2
            },
            {
                rect: [260, 210],
                interval: normal_a2
            },
            {
                rect: [520, 210],
                interval: normal_a2
            },
            {
                rect: [780, 210],
                interval: normal_a2
            },
            {
                rect: [1040, 210],
                interval: normal_a2
            },
            {
                rect: [0, 420],
                interval: 160
            },
            ]
        },
        21 : {
            id: 21,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -108,
            my: 0,
            attackt: normal_a1 * 5,
            rx: -98,
            sx: 62,
            sy: 0,
            bx: 75,
            by: 0,
            lx: 128,
            ly: 126,
            px: 36,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            ]
        },
        22 : {
            id: 22,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -110,
            my: 0,
            attackt: normal_a * 7,
            rx: -96,
            sx: 62,
            sy: 0,
            bx: 75,
            by: 0,
            lx: 128,
            ly: 126,
            px: 36,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            {
                rect: [0, 210],
                interval: normal_a
            },
            {
                rect: [260, 210],
                interval: normal_a
            },
            ]
        },
        23 : {
            id: 23,
            herotype: 1,
            width: 301,
            height: 210,
            mx: -125,
            my: 0,
            attackt: normal_a1 * 7,
            rx: -60,
            sx: 78,
            sy: 0,
            bx: 93,
            by: 0,
            lx: 145,
            ly: 126,
            px: 58,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [301, 0],
                interval: normal_s
            },
            {
                rect: [602, 0],
                interval: normal_s
            },
            {
                rect: [903, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [301, 0],
                interval: normal_a1
            },
            {
                rect: [602, 0],
                interval: normal_a1
            },
            {
                rect: [903, 0],
                interval: normal_a1
            },
            {
                rect: [1204, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            {
                rect: [301, 210],
                interval: normal_a1
            },
            ]
        },
        24 : {
            id: 24,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -124,
            my: 0,
            attackt: normal_a * 5,
            rx: -68,
            sx: 76,
            sy: 0,
            bx: 90,
            by: 0,
            lx: 142,
            ly: 126,
            px: 50,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        25 : {
            id: 25,
            herotype: 1,
            width: 301,
            height: 210,
            mx: -140,
            my: 0,
            attackt: normal_a * 5,
            rx: -38,
            sx: 88,
            sy: 0,
            bx: 103,
            by: 0,
            lx: 155,
            ly: 126,
            px: 68,
            at: normal_at,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [301, 0],
                interval: normal_s
            },
            {
                rect: [602, 0],
                interval: normal_s
            },
            {
                rect: [903, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [301, 0],
                interval: normal_a
            },
            {
                rect: [602, 0],
                interval: normal_a
            },
            {
                rect: [903, 0],
                interval: normal_a
            },
            {
                rect: [1204, 0],
                interval: normal_a,
            },
            ]
        },
        26 : {
            id: 26,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -86,
            my: 0,
            attackt: normal_a * 5,
            rx: -140,
            sx: 36,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 105,
            ly: 128,
            px: 10,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        27 : {
            id: 27,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -86,
            my: 0,
            attackt: normal_a * 5,
            rx: -140,
            sx: 36,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 105,
            ly: 128,
            px: 10,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        28 : {
            id: 28,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -86,
            my: 0,
            attackt: normal_a * 5,
            rx: -140,
            sx: 36,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 105,
            ly: 128,
            px: 10,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        29 : {
            id: 29,
            herotype: 2,
            width: 182,
            height: 160,
            mx: -40,
            my: 0,
            attackt: normal_a * 6,
            //攻击总时间
            rx: -234,
            sx: -8,
            sy: 0,
            bx: 8,
            by: 0,
            lx: 50,
            ly: 132,
            px: -34,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [182, 0],
                interval: normal_s
            },
            {
                rect: [364, 0],
                interval: normal_s
            },
            {
                rect: [546, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [182, 0],
                interval: normal_a
            },
            {
                rect: [364, 0],
                interval: normal_a
            },
            {
                rect: [546, 0],
                interval: normal_a
            },
            {
                rect: [728, 0],
                interval: normal_a
            },
            {
                rect: [0, 160],
                interval: normal_a
            },
            ]
        },
        30 : {
            id: 30,
            herotype: 2,
            width: 182,
            height: 160,
            mx: -40,
            my: 0,
            attackt: normal_a * 6,
            //攻击总时间
            rx: -234,
            sx: -8,
            sy: 0,
            bx: 8,
            by: 0,
            lx: 50,
            ly: 132,
            px: -34,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [182, 0],
                interval: normal_s
            },
            {
                rect: [364, 0],
                interval: normal_s
            },
            {
                rect: [546, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [182, 0],
                interval: normal_a
            },
            {
                rect: [364, 0],
                interval: normal_a
            },
            {
                rect: [546, 0],
                interval: normal_a
            },
            {
                rect: [728, 0],
                interval: normal_a
            },
            {
                rect: [0, 160],
                interval: normal_a
            },
            ]
        },
        31 : {
            id: 31,
            herotype: 2,
            width: 182,
            height: 160,
            mx: -40,
            my: 0,
            attackt: normal_a * 6,
            //攻击总时间
            rx: -234,
            sx: -8,
            sy: 0,
            bx: 8,
            by: 0,
            lx: 50,
            ly: 132,
            px: -34,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [182, 0],
                interval: normal_s
            },
            {
                rect: [364, 0],
                interval: normal_s
            },
            {
                rect: [546, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [182, 0],
                interval: normal_a
            },
            {
                rect: [364, 0],
                interval: normal_a
            },
            {
                rect: [546, 0],
                interval: normal_a
            },
            {
                rect: [728, 0],
                interval: normal_a
            },
            {
                rect: [0, 160],
                interval: normal_a
            },
            ]
        },
        32 : {
            id: 32,
            herotype: 1,
            width: 301,
            height: 210,
            mx: -125,
            my: 0,
            attackt: normal_a1 * 7,
            rx: -60,
            sx: 78,
            sy: 0,
            bx: 93,
            by: 0,
            lx: 145,
            ly: 126,
            px: 58,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [301, 0],
                interval: normal_s
            },
            {
                rect: [602, 0],
                interval: normal_s
            },
            {
                rect: [903, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [301, 0],
                interval: normal_a1
            },
            {
                rect: [602, 0],
                interval: normal_a1
            },
            {
                rect: [903, 0],
                interval: normal_a1
            },
            {
                rect: [1204, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            {
                rect: [301, 210],
                interval: normal_a1
            },
            ]
        },
        33 : {
            id: 33,
            herotype: 1,
            width: 301,
            height: 210,
            mx: -135,
            my: 0,
            attackt: normal_a1 * 4,
            rx: -48,
            sx: 83,
            sy: 0,
            bx: 98,
            by: 0,
            lx: 150,
            ly: 126,
            px: 63,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [301, 0],
                interval: normal_s
            },
            {
                rect: [602, 0],
                interval: normal_s
            },
            {
                rect: [903, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [301, 0],
                interval: normal_a1
            },
            {
                rect: [602, 0],
                interval: normal_a1
            },
            {
                rect: [903, 0],
                interval: normal_a1
            },
            ]
        },
        34 : {
            id: 34,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -84,
            my: 0,
            attackt: normal_a1 * 9,
            rx: -148,
            sx: 33,
            sy: 0,
            bx: 50,
            by: 0,
            lx: 102,
            ly: 126,
            px: 10,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            {
                rect: [260, 210],
                interval: normal_a1
            },
            {
                rect: [520, 210],
                interval: normal_a1
            },
            {
                rect: [780, 210],
                interval: normal_a1
            },
            ]
        },
        35 : {
            id: 35,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -90,
            my: 0,
            attackt: normal_a1 * 6,
            rx: -136,
            sx: 38,
            sy: 0,
            bx: 55,
            by: 0,
            lx: 108,
            ly: 126,
            px: 16,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            ]
        },
        36 : {
            id: 36,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -90,
            my: 0,
            attackt: normal_a1 * 6,
            rx: -136,
            sx: 38,
            sy: 0,
            bx: 55,
            by: 0,
            lx: 108,
            ly: 126,
            px: 16,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            ]
        },
        37 : {
            id: 37,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -90,
            my: 0,
            attackt: normal_a1 * 6,
            rx: -136,
            sx: 38,
            sy: 0,
            bx: 55,
            by: 0,
            lx: 108,
            ly: 126,
            px: 16,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            ]
        },
        38 : {
            id: 38,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -72,
            my: 0,
            attackt: normal_a2 * 9,
            rx: -170,
            sx: 22,
            sy: 0,
            bx: 38,
            by: 0,
            lx: 90,
            ly: 126,
            px: -2,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [315, 0],
                interval: normal_a2
            },
            {
                rect: [630, 0],
                interval: normal_a2
            },
            {
                rect: [945, 0],
                interval: normal_a2
            },
            {
                rect: [1260, 0],
                interval: normal_a2
            },
            {
                rect: [0, 253],
                interval: normal_a2
            },
            {
                rect: [315, 253],
                interval: normal_a2
            },
            {
                rect: [630, 253],
                interval: normal_a2
            },
            {
                rect: [945, 253],
                interval: normal_a2
            },
            ]
        },
        39 : {
            id: 39,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -100,
            my: 0,
            attackt: normal_a1 * 7,
            rx: -116,
            sx: 48,
            sy: 0,
            bx: 65,
            by: 0,
            lx: 118,
            ly: 126,
            px: 26,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            {
                rect: [260, 210],
                interval: normal_a1
            },
            ]
        },
        40 : {
            id: 40,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -90,
            my: 0,
            attackt: normal_a1 * 6,
            rx: -136,
            sx: 38,
            sy: 0,
            bx: 55,
            by: 0,
            lx: 108,
            ly: 126,
            px: 16,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            ]
        },
        41 : {
            id: 41,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -84,
            my: 0,
            attackt: normal_a1 * 7,
            rx: -148,
            sx: 32,
            sy: 0,
            bx: 50,
            by: 0,
            lx: 102,
            ly: 126,
            px: 10,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            {
                rect: [260, 210],
                interval: normal_a1
            },
            ]
        },
        42 : {
            id: 42,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -102,
            my: 0,
            attackt: normal_a1 * 7,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            ]
        },
        43 : {
            id: 43,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -120,
            my: 0,
            attackt: normal_a2 * 10,
            rx: -76,
            sx: 68,
            sy: 0,
            bx: 85,
            by: 0,
            lx: 138,
            ly: 126,
            px: 46,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [260, 0],
                interval: normal_a2
            },
            {
                rect: [520, 0],
                interval: normal_a2
            },
            {
                rect: [780, 0],
                interval: normal_a2
            },
            {
                rect: [1040, 0],
                interval: normal_a2
            },
            {
                rect: [0, 210],
                interval: normal_a2
            },
            {
                rect: [260, 210],
                interval: normal_a2
            },
            {
                rect: [520, 210],
                interval: normal_a2
            },
            {
                rect: [780, 210],
                interval: normal_a2
            },
            {
                rect: [1040, 210],
                interval: normal_a2
            },
            ]
        },
        44 : {
            id: 44,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -112,
            my: 0,
            attackt: normal_a1 * 6,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 38,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            ]
        },
        45 : {
            id: 45,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -122,
            my: 0,
            attackt: normal_a1 * 6,
            rx: -70,
            sx: 72,
            sy: 0,
            bx: 88,
            by: 0,
            lx: 140,
            ly: 126,
            px: 48,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            ]
        },
        46 : {
            id: 46,
            herotype: 1,
            width: 301,
            height: 210,
            mx: -155,
            my: 0,
            attackt: normal_a2 * 9,
            rx: -8,
            sx: 103,
            sy: 0,
            bx: 118,
            by: 0,
            lx: 170,
            ly: 126,
            px: 83,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [301, 0],
                interval: normal_s
            },
            {
                rect: [602, 0],
                interval: normal_s
            },
            {
                rect: [903, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [301, 0],
                interval: normal_a2
            },
            {
                rect: [602, 0],
                interval: normal_a2
            },
            {
                rect: [903, 0],
                interval: normal_a2
            },
            {
                rect: [1204, 0],
                interval: normal_a2
            },
            {
                rect: [0, 210],
                interval: normal_a2
            },
            {
                rect: [301, 210],
                interval: normal_a2
            },
            {
                rect: [602, 210],
                interval: normal_a2
            },
            {
                rect: [903, 210],
                interval: normal_a2
            },
            ]
        },
        47 : {
            id: 47,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -158,
            my: 0,
            attackt: normal_a1 * 5,
            rx: 2,
            sx: 108,
            sy: 0,
            bx: 124,
            by: 0,
            lx: 176,
            ly: 126,
            px: 84,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            ]
        },
        48 : {
            id: 48,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -142,
            my: 0,
            attackt: normal_a2 * 7,
            rx: -30,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 68,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [315, 0],
                interval: normal_a2
            },
            {
                rect: [630, 0],
                interval: normal_a2
            },
            {
                rect: [945, 0],
                interval: normal_a2
            },
            {
                rect: [1260, 0],
                interval: normal_a2
            },
            {
                rect: [0, 253],
                interval: normal_a2
            },
            {
                rect: [315, 253],
                interval: normal_a2
            },
            ]
        },
        49 : {
            id: 49,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -80,
            my: -4,
            attackt: normal_a1 * 9,
            rx: -156,
            sx: 28,
            sy: 0,
            bx: 45,
            by: 0,
            lx: 98,
            ly: 126,
            px: 6,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [260, 0],
                interval: normal_a1
            },
            {
                rect: [520, 0],
                interval: normal_a1
            },
            {
                rect: [780, 0],
                interval: normal_a1
            },
            {
                rect: [1040, 0],
                interval: normal_a1
            },
            {
                rect: [0, 210],
                interval: normal_a1
            },
            {
                rect: [260, 210],
                interval: normal_a1
            },
            {
                rect: [520, 210],
                interval: normal_a1
            },
            {
                rect: [780, 210],
                interval: normal_a1
            },
            ]
        },
        50 : {
            id: 50,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -76,
            my: 0,
            attackt: normal_a2 * 10,
            rx: -162,
            sx: 26,
            sy: 0,
            bx: 42,
            by: 0,
            lx: 94,
            ly: 126,
            px: 2,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [315, 0],
                interval: normal_a2
            },
            {
                rect: [630, 0],
                interval: normal_a2
            },
            {
                rect: [945, 0],
                interval: normal_a2
            },
            {
                rect: [1260, 0],
                interval: normal_a2
            },
            {
                rect: [0, 253],
                interval: normal_a2
            },
            {
                rect: [315, 253],
                interval: normal_a2
            },
            {
                rect: [630, 253],
                interval: normal_a2
            },
            {
                rect: [945, 253],
                interval: normal_a2
            },
            {
                rect: [1260, 253],
                interval: normal_a2
            },
            ]
        },
        51 : {
            id: 51,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -110,
            my: 0,
            attackt: normal_a * 5,
            rx: -96,
            sx: 58,
            sy: 0,
            bx: 75,
            by: 0,
            lx: 128,
            ly: 126,
            px: 36,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            ]
        },
        52 : {
            id: 52,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -142,
            my: 0,
            attackt: normal_a1 * 7,
            rx: -30,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 68,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            ]
        },
        53 : {
            id: 53,
            herotype: 2,
            width: 260,
            height: 210,
            mx: -90,
            my: 0,
            attackt: normal_a * 8 + 40,
            rx: -136,
            sx: 38,
            sy: 0,
            bx: 55,
            by: 0,
            lx: 108,
            ly: 126,
            px: 16,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            {
                rect: [0, 210],
                interval: normal_a
            },
            {
                rect: [260, 210],
                interval: normal_a
            },
            {
                rect: [520, 210],
                interval: (normal_a + 40)
            },
            ]
        },
        54 : {
            id: 54,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -142,
            my: 0,
            attackt: normal_a2 * 7 + normal_a * 3,
            rx: -30,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 68,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [315, 0],
                interval: normal_a2
            },
            {
                rect: [630, 0],
                interval: normal_a2
            },
            {
                rect: [945, 0],
                interval: normal_a2
            },
            {
                rect: [1260, 0],
                interval: normal_a2
            },
            {
                rect: [0, 253],
                interval: normal_a2
            },
            {
                rect: [315, 253],
                interval: normal_a2
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            {
                rect: [945, 253],
                interval: normal_a
            },
            {
                rect: [1260, 253],
                interval: normal_a
            },
            ]
        },
        55 : {
            id: 55,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -112,
            my: 0,
            attackt: normal_a2 * 9,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 38,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [315, 0],
                interval: normal_a2
            },
            {
                rect: [630, 0],
                interval: normal_a2
            },
            {
                rect: [945, 0],
                interval: normal_a2
            },
            {
                rect: [1260, 0],
                interval: normal_a2
            },
            {
                rect: [0, 253],
                interval: normal_a2
            },
            {
                rect: [315, 253],
                interval: normal_a2
            },
            {
                rect: [630, 253],
                interval: normal_a2
            },
            {
                rect: [945, 253],
                interval: normal_a2
            },
            ]
        },
        56 : {
            id: 56,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -142,
            my: 0,
            attackt: normal_a2 * 7,
            rx: -30,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 68,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [315, 0],
                interval: normal_a2
            },
            {
                rect: [630, 0],
                interval: normal_a2
            },
            {
                rect: [945, 0],
                interval: normal_a2
            },
            {
                rect: [1260, 0],
                interval: normal_a2
            },
            {
                rect: [0, 253],
                interval: normal_a2
            },
            {
                rect: [315, 253],
                interval: normal_a2
            },
            ]
        },
        57 : {
            id: 57,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -82,
            my: 0,
            attackt: normal_a * 9,
            rx: -150,
            sx: 32,
            sy: 0,
            bx: 48,
            by: 0,
            lx: 100,
            ly: 126,
            px: 8,
            ax: -36,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            {
                rect: [945, 253],
                interval: normal_a
            },
            ]
        },
        58 : {
            id: 58,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -98,
            my: 0,
            attackt: normal_a * 11,
            rx: -118,
            sx: 48,
            sy: 0,
            bx: 64,
            by: 0,
            lx: 116,
            ly: 126,
            px: 24,
            ax: -50,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            {
                rect: [945, 253],
                interval: normal_a
            },
            {
                rect: [1260, 253],
                interval: normal_a
            },
            ]
        },
        59 : {
            id: 59,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -132,
            my: 0,
            attackt: normal_a * 8,
            rx: -50,
            sx: 82,
            sy: 0,
            bx: 98,
            by: 0,
            lx: 150,
            ly: 126,
            px: 58,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        60 : {
            id: 60,
            herotype: 1,
            width: 260,
            height: 210,
            mx: -120,
            my: 0,
            attackt: normal_a * 6,
            rx: -76,
            sx: 68,
            sy: 0,
            bx: 85,
            by: 0,
            lx: 138,
            ly: 126,
            px: 46,
            animations: [{
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [260, 0],
                interval: normal_s
            },
            {
                rect: [520, 0],
                interval: normal_s
            },
            {
                rect: [780, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [260, 0],
                interval: normal_a
            },
            {
                rect: [520, 0],
                interval: normal_a
            },
            {
                rect: [780, 0],
                interval: normal_a
            },
            {
                rect: [1040, 0],
                interval: normal_a
            },
            {
                rect: [0, 210],
                interval: normal_a
            },
            ]
        },
        61 : {
            id: 61,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -112,
            my: 0,
            attackt: normal_a * 6,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 38,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            ]
        },
        62 : {
            id: 62,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -158,
            my: 0,
            attackt: normal_a * 6,
            rx: 2,
            sx: 108,
            sy: 0,
            bx: 124,
            by: 0,
            lx: 176,
            ly: 126,
            px: 84,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            ]
        },
        63 : {
            id: 63,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -88,
            my: 0,
            attackt: normal_a * 7 + 20 * 7,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 14,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a + 20
            },
            {
                rect: [315, 0],
                interval: normal_a + 20
            },
            {
                rect: [630, 0],
                interval: normal_a + 20
            },
            {
                rect: [945, 0],
                interval: normal_a + 20
            },
            {
                rect: [1260, 0],
                interval: normal_a + 20
            },
            {
                rect: [0, 253],
                interval: normal_a + 20
            },
            {
                rect: [315, 253],
                interval: normal_a + 20
            },
            ]
        },
        64 : {
            id: 64,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -122,
            my: 0,
            attackt: normal_a1 * 6,
            rx: -70,
            sx: 72,
            sy: 0,
            bx: 88,
            by: 0,
            lx: 140,
            ly: 126,
            px: 48,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            ]
        },
        65 : {
            id: 65,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -172,
            my: 6,
            attackt: normal_a1 * 7 + normal_a,
            rx: 30,
            sx: 122,
            sy: 0,
            bx: 138,
            by: 0,
            lx: 190,
            ly: 126,
            px: 98,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        66 : {
            id: 66,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -152,
            my: 0,
            attackt: normal_a1 * 7,
            rx: -10,
            sx: 102,
            sy: 0,
            bx: 118,
            by: 0,
            lx: 170,
            ly: 126,
            px: 78,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            ]
        },
        67 : {
            id: 67,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -112,
            my: 0,
            attackt: normal_a1 * 8,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 38,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        68 : {
            id: 68,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -88,
            my: 0,
            attackt: normal_a1 * 12,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 14,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            {
                rect: [0, 506],
                interval: normal_a1
            },
            {
                rect: [315, 506],
                interval: normal_a1
            },
            ]
        },
        69 : {
            id: 69,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -110,
            my: 0,
            attackt: normal_a1 * 12,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 36,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            {
                rect: [0, 506],
                interval: normal_a1
            },
            {
                rect: [315, 506],
                interval: normal_a1
            },
            ]
        },
        70 : {
            id: 70,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -142,
            my: 4,
            attackt: normal_a1 * 10,
            rx: -28,
            sx: 94,
            sy: 0,
            bx: 110,
            by: 0,
            lx: 160,
            ly: 126,
            px: 70,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            ]
        },
        71 : {
            id: 71,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -58,
            my: 0,
            attackt: normal_a1 * 10,
            rx: -198,
            sx: 8,
            sy: 0,
            bx: 24,
            by: 0,
            lx: 76,
            ly: 126,
            px: -16,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            ]
        },
        72 : {
            id: 72,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -88,
            my: 0,
            attackt: normal_a1 * 9,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 14,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            ]
        },
        73 : {
            id: 73,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -52,
            my: 0,
            attackt: normal_a1 * 9,
            rx: -210,
            sx: 2,
            sy: 0,
            bx: 18,
            by: 0,
            lx: 70,
            ly: 126,
            px: -22,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            ]
        },
        74 : {
            id: 74,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -94,
            my: 0,
            attackt: normal_a1 * 11,
            rx: -126,
            sx: 44,
            sy: 0,
            bx: 60,
            by: 0,
            lx: 112,
            ly: 126,
            px: 20,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            {
                rect: [0, 506],
                interval: normal_a1
            },
            ]
        },
        75 : {
            id: 75,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -108,
            my: 20,
            attackt: normal_a1 * 7 + normal_a,
            rx: -98,
            sx: 58,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 126,
            ly: 126,
            px: 34,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        76 : {
            id: 76,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -78,
            my: 10,
            attackt: normal_a1 * 9,
            rx: -158,
            sx: 28,
            sy: 0,
            bx: 44,
            by: 0,
            lx: 96,
            ly: 126,
            px: 4,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            ]
        },
        77 : {
            id: 77,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -130,
            my: 10,
            attackt: normal_a1 * 9,
            rx: -54,
            sx: 80,
            sy: 0,
            bx: 96,
            by: 0,
            lx: 158,
            ly: 126,
            px: 56,
            animations: [ //false
            {
                rect: [0, 0],
                label: "stand",
                interval: normal_s
            },
            {
                rect: [315, 0],
                interval: normal_s
            },
            {
                rect: [630, 0],
                interval: normal_s
            },
            {
                rect: [945, 0],
                jump: "stand",
                interval: normal_s
            },
            ],
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            ]
        },
        78 : {
            id: 78,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -152,
            my: 0,
            attackt: normal_a2 * 7,
            rx: -10,
            sx: 102,
            sy: 0,
            bx: 118,
            by: 0,
            lx: 170,
            ly: 126,
            px: 78,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [315, 0],
                interval: normal_a2
            },
            {
                rect: [630, 0],
                interval: normal_a2
            },
            {
                rect: [945, 0],
                interval: normal_a2
            },
            {
                rect: [1260, 0],
                interval: normal_a2
            },
            {
                rect: [0, 253],
                interval: normal_a2
            },
            {
                rect: [315, 253],
                interval: normal_a2
            },
            ]
        },
        79 : {
            id: 79,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -112,
            my: 0,
            attackt: normal_a * 11,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 38,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a2
            },
            {
                rect: [315, 0],
                interval: normal_a2
            },
            {
                rect: [630, 0],
                interval: normal_a2
            },
            {
                rect: [945, 0],
                interval: normal_a2
            },
            {
                rect: [1260, 0],
                interval: normal_a2
            },
            {
                rect: [0, 253],
                interval: normal_a2
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            {
                rect: [945, 253],
                interval: normal_a
            },
            {
                rect: [1260, 253],
                interval: normal_a
            },
            {
                rect: [0, 506],
                interval: normal_a
            }
            ]
        },
        80 : {
            id: 80,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -102,
            my: 0,
            attackt: normal_a1 * 10,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            ]
        },
        81 : {
            id: 81,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -102,
            my: 0,
            attackt: normal_a * 7,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        82 : {
            id: 82,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -82,
            my: 0,
            attackt: normal_a1 * 11,
            rx: -150,
            sx: 32,
            sy: 0,
            bx: 48,
            by: 0,
            lx: 100,
            ly: 126,
            px: 8,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            {
                rect: [0, 506],
                interval: normal_a1
            },
            ]
        },
        83 : {
            id: 83,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -122,
            my: 0,
            attackt: normal_a * 6,
            rx: -70,
            sx: 72,
            sy: 0,
            bx: 88,
            by: 0,
            lx: 140,
            ly: 126,
            px: 48,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            ]
        },
        84 : {
            id: 84,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -122,
            my: 0,
            attackt: normal_a1 * 10,
            rx: -70,
            sx: 72,
            sy: 0,
            bx: 88,
            by: 0,
            lx: 140,
            ly: 126,
            px: 48,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            ]
        },
        85 : {
            id: 85,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -142,
            my: 0,
            attackt: normal_a * 9,
            rx: -30,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 68,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            {
                rect: [945, 253],
                interval: normal_a
            },
            ]
        },
        86 : {
            id: 86,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -122,
            my: 0,
            attackt: normal_a * 9,
            rx: -70,
            sx: 72,
            sy: 0,
            bx: 88,
            by: 0,
            lx: 140,
            ly: 126,
            px: 48,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            {
                rect: [945, 253],
                interval: normal_a
            },
            ]
        },
        87 : {
            id: 87,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -108,
            my: 0,
            attackt: normal_a1 * 10,
            rx: -98,
            sx: 58,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 126,
            ly: 126,
            px: 34,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            ]
        },
        88 : {
            id: 88,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -138,
            my: 0,
            attackt: normal_a1 * 8,
            rx: -38,
            sx: 88,
            sy: 0,
            bx: 104,
            by: 0,
            lx: 156,
            ly: 126,
            px: 64,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        89 : {
            id: 89,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -108,
            my: 14,
            attackt: normal_a1 * 9,
            rx: -98,
            sx: 58,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 126,
            ly: 126,
            px: 34,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            ]
        },
        90 : {
            id: 90,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -118,
            my: 10,
            attackt: normal_a1 * 9,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 44,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            ]
        },
        91 : {
            id: 91,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -88,
            my: 0,
            attackt: normal_a1 * 12,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 14,
            ax: -50,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            {
                rect: [945, 253],
                interval: normal_a1
            },
            {
                rect: [1260, 253],
                interval: normal_a1
            },
            ]
        },
        92 : {
            id: 92,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -112,
            my: 0,
            attackt: normal_a * 7,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 38,
            ax: -26,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        93 : {
            id: 93,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -102,
            my: 0,
            attackt: normal_a * 6,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            ]
        },
        94 : {
            id: 94,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -144,
            my: 12,
            attackt: normal_a * 5,
            rx: -26,
            sx: 94,
            sy: 0,
            bx: 110,
            by: 0,
            lx: 162,
            ly: 126,
            px: 70,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            }
            ]
        },
        95 : {
            id: 95,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -112,
            my: 0,
            attackt: normal_a * 5,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 38,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            }
            ]
        },
        96 : {
            id: 96,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -108,
            my: 0,
            attackt: normal_a * 6,
            rx: -98,
            sx: 58,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 126,
            ly: 126,
            px: 34,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            ]
        },
        97 : {
            id: 97,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -88,
            my: 0,
            attackt: normal_a * 8,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 14,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            }
            ]
        },
        98 : {
            id: 98,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -118,
            my: 0,
            attackt: normal_a1 * 8,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 44,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            }
            ]
        },
        99 : {/* 大BOSS，需要单独处理 */
            id: 99,
            herotype: 2,
            width: 640,
            height: 320,
            mx: -248,
            my: 10,
            attackt: normal_a1 * 8,
            rx: -142,
            sx: 198,
            sy: 0,
            bx: 214,
            by: 0,
            lx: 266,
            ly: 126,
            px: 174,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [640, 0],
                interval: normal_a1
            },
            {
                rect: [1280, 0],
                interval: normal_a1
            },
            {
                rect: [1920, 0],
                interval: normal_a1
            },
            {
                rect: [2560, 0],
                interval: normal_a1
            },
            {
                rect: [0, 320],
                interval: normal_a1
            },
            {
                rect: [640, 320],
                interval: normal_a1
            },
            {
                rect: [1280, 320],
                interval: normal_a1
            },
            ]
        },
        100 : {
            id: 100,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -118,
            my: -4,
            attackt: normal_a1 * 8,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 44,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        101 : {
            id: 101,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -88,
            my: 0,
            attackt: normal_a * 6,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 14,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            ]
        },
        102 : {
            id: 102,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -108,
            my: 16,
            attackt: normal_a1 * 7,
            rx: -98,
            sx: 58,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 126,
            ly: 126,
            px: 34,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            ]
        },
        103 : {
            id: 103,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -134,
            my: 0,
            attackt: normal_a1 * 7,
            rx: -46,
            sx: 84,
            sy: 0,
            bx: 100,
            by: 0,
            lx: 152,
            ly: 126,
            px: 60,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            ]
        },
        104 : {
            id: 104,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -88,
            my: 0,
            attackt: normal_a1 * 7,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 14,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            ]
        },
        105 : {
            id: 105,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -94,
            my: 0,
            attackt: normal_a1 * 8,
            rx: -126,
            sx: 44,
            sy: 0,
            bx: 60,
            by: 0,
            lx: 112,
            ly: 126,
            px: 20,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        106 : {
            id: 106,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -88,
            my: -2,
            attackt: normal_a1 * 8,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 14,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        107 : {
            id: 107,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -148,
            my: 4,
            attackt: normal_a1 * 8,
            rx: -18,
            sx: 98,
            sy: 0,
            bx: 114,
            by: 0,
            lx: 166,
            ly: 126,
            px: 74,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        108 : {
            id: 108,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -102,
            my: 0,
            attackt: normal_a1 * 8,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        109 : {
            id: 109,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -118,
            my: 4,
            attackt: normal_a1 * 8,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 44,
            ax: -24,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        110 : {
            id: 110,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -118,
            my: 2,
            attackt: normal_a1 * 8,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 44,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        111 : {
            id: 111,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -68,
            my: 0,
            attackt: normal_a1 * 8,
            rx: -178,
            sx: 18,
            sy: 0,
            bx: 34,
            by: 0,
            lx: 86,
            ly: 126,
            px: -6,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        112 : {
            id: 112,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -104,
            my: 4,
            attackt: normal_a * 6,
            rx: -106,
            sx: 54,
            sy: 0,
            bx: 70,
            by: 0,
            lx: 122,
            ly: 126,
            px: 30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            ]
        },
        113 : {
            id: 113,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -62,
            my: -4,
            attackt: normal_a1 * 8,
            rx: -190,
            sx: 12,
            sy: 0,
            bx: 28,
            by: 0,
            lx: 80,
            ly: 126,
            px: -12,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        114 : {
            id: 114,
            herotype: 1,
            width: 315,
            height: 253,
            mx: -102,
            my: 20,
            attackt: normal_a1 * 8,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            ax: -50,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            {
                rect: [630, 253],
                interval: normal_a1
            },
            ]
        },
        115 : {
            id: 115,
            herotype: 2,
            width: 315,
            height: 253,
            mx: -84,
            my: 16,
            attackt: normal_a1 * 7,
            rx: -146,
            sx: 34,
            sy: 0,
            bx: 50,
            by: 0,
            lx: 102,
            ly: 126,
            px: 10,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a1
            },
            {
                rect: [315, 0],
                interval: normal_a1
            },
            {
                rect: [630, 0],
                interval: normal_a1
            },
            {
                rect: [945, 0],
                interval: normal_a1
            },
            {
                rect: [1260, 0],
                interval: normal_a1
            },
            {
                rect: [0, 253],
                interval: normal_a1
            },
            {
                rect: [315, 253],
                interval: normal_a1
            },
            ]
        },
        116 : {
            id: 116,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -96,
            my: 10,
            rx: -122,
            sx: 46,
            sy: 0,
            bx: 62,
            by: 0,
            lx: 114,
            ly: 126,
            px: 22,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        117 : {
            id: 117,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -96,
            my: 10,
            rx: -122,
            sx: 46,
            sy: 0,
            bx: 62,
            by: 0,
            lx: 114,
            ly: 126,
            px: 22,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        151 : {
            id: 151,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -91,
            my: 10,
            rx: -132,
            sx: 41,
            sy: 0,
            bx: 57,
            by: 0,
            lx: 109,
            ly: 126,
            px: 17,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        152 : {
            id: 152,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -96,
            my: 15,
            rx: -122,
            sx: 46,
            sy: 0,
            bx: 62,
            by: 0,
            lx: 114,
            ly: 126,
            px: 22,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        153 : {
            id: 153,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -91,
            my: 20,
            rx: -132,
            sx: 41,
            sy: 0,
            bx: 57,
            by: 0,
            lx: 109,
            ly: 126,
            px: 17,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        154 : {
            id: 154,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -96,
            my: 10,
            rx: -122,
            sx: 46,
            sy: 0,
            bx: 62,
            by: 0,
            lx: 114,
            ly: 126,
            px: 22,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        155 : {
            id: 155,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -106,
            my: 10,
            rx: -102,
            sx: 56,
            sy: 0,
            bx: 72,
            by: 0,
            lx: 124,
            ly: 126,
            px: 32,
            ax: -20, 
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        156 : {
            id: 156,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -106,
            my: 10,
            rx: -102,
            sx: 56,
            sy: 0,
            bx: 72,
            by: 0,
            lx: 124,
            ly: 126,
            px: 32,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        157 : {
            id: 157,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -111,
            my: 0,
            rx: -92,
            sx: 61,
            sy: 0,
            bx: 77,
            by: 0,
            lx: 129,
            ly: 126,
            px: 37,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        158 : {
            id: 158,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -96,
            my: 5,
            rx: -122,
            sx: 46,
            sy: 0,
            bx: 62,
            by: 0,
            lx: 114,
            ly: 126,
            px: 22,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        159 : {
            id: 159,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -121,
            my: 5,
            rx: -72,
            sx: 71,
            sy: 0,
            bx: 87,
            by: 0,
            lx: 139,
            ly: 126,
            px: 47,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        160 : {
            id: 160,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -81,
            my: 15,
            rx: -152,
            sx: 31,
            sy: 0,
            bx: 47,
            by: 0,
            lx: 99,
            ly: 126,
            px: 7,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        161 : {
            id: 161,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -71,
            my: 5,
            rx: -172,
            sx: 21,
            sy: 0,
            bx: 37,
            by: 0,
            lx: 89,
            ly: 126,
            px: -3,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        162 : {
            id: 162,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -81,
            my: 10,
            rx: -152,
            sx: 31,
            sy: 0,
            bx: 47,
            by: 0,
            lx: 99,
            ly: 126,
            px: 7,
            ax: -40,             
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        163 : {
            id: 163,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -121,
            my: 30,
            rx: -72,
            sx: 71,
            sy: 0,
            bx: 87,
            by: 0,
            lx: 139,
            ly: 126,
            px: 47,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        164 : {
            id: 164,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -86,
            my: 5,
            rx: -142,
            sx: 36,
            sy: 0,
            bx: 52,
            by: 0,
            lx: 104,
            ly: 126,
            px: 12,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        201 : {
            id: 201,
            herotype:2,
            width: 360,
            height: 180,
            mx: -102,
            my: 0,
            attackt: normal_a * 8,
            rx: -140,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [360, 0],
                interval: normal_a
            },
            {
                rect: [720, 0],
                interval: normal_a
            },
            {
                rect: [1080, 0],
                interval: normal_a
            },
            {
                rect: [1440, 0],
                interval: normal_a
            },
            {
                rect: [0, 180],
                interval: normal_a
            },
            {
                rect: [360, 180],
                interval: normal_a
            },
            {
                rect: [720, 180],
                interval: normal_a
            },
            ]
        },
        202 : {
            id: 202,
            herotype: 2,
            width: 524,
            height: 262,
            mx: -142,
            my: 10,
            attackt: normal_a * 8,
            rx: -230,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 68,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [524, 0],
                interval: normal_a
            },
            {
                rect: [1048, 0],
                interval: normal_a
            },
            {
                rect: [1572, 0],
                interval: normal_a
            },
            {
                rect: [2096, 0],
                interval: normal_a
            },
            {
                rect: [0, 262],
                interval: normal_a
            },
            {
                rect: [524, 262],
                interval: normal_a
            },
            {
                rect: [1048, 262],
                interval: normal_a
            },
            ]
        },
        203 : {
            id: 203,
            herotype: 2,
            width: 640,
            height: 320,
            mx: -188,
            my: 20,
            attackt: normal_a * 8,
            rx: -262,
            sx: 138,
            sy: 0,
            bx: 154,
            by: 0,
            lx: 206,
            ly: 126,
            px: 114,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [640, 0],
                interval: normal_a
            },
            {
                rect: [1280, 0],
                interval: normal_a
            },
            {
                rect: [1920, 0],
                interval: normal_a
            },
            {
                rect: [2560, 0],
                interval: normal_a
            },
            {
                rect: [0, 320],
                interval: normal_a
            },
            {
                rect: [640, 320],
                interval: normal_a
            },
            {
                rect: [1280, 320],
                interval: normal_a
            },
            ]
        },
        204 : {
            id: 204,
            herotype:2,
            width: 360,
            height: 180,
            mx: -122,
            my: 10,
            attackt: normal_a * 8,
            rx: -140,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [360, 0],
                interval: normal_a
            },
            {
                rect: [720, 0],
                interval: normal_a
            },
            {
                rect: [1080, 0],
                interval: normal_a
            },
            {
                rect: [1440, 0],
                interval: normal_a
            },
            {
                rect: [0, 180],
                interval: normal_a
            },
            {
                rect: [360, 180],
                interval: normal_a
            },
            {
                rect: [720, 180],
                interval: normal_a
            },
            ]
        },
        205 : {
            id: 205,
            herotype: 2,
            width: 524,
            height: 262,
            mx: -162,
            my: 10,
            attackt: normal_a * 8,
            rx: -230,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 68,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [524, 0],
                interval: normal_a
            },
            {
                rect: [1048, 0],
                interval: normal_a
            },
            {
                rect: [1572, 0],
                interval: normal_a
            },
            {
                rect: [2096, 0],
                interval: normal_a
            },
            {
                rect: [0, 262],
                interval: normal_a
            },
            {
                rect: [524, 262],
                interval: normal_a
            },
            {
                rect: [1048, 262],
                interval: normal_a
            },
            ]
        },
        206 : {
            id: 206,
            herotype: 2,
            width: 640,
            height: 320,
            mx: -208,
            my: 20,
            attackt: normal_a * 8,
            rx: -280,
            sx: 138,
            sy: 0,
            bx: 154,
            by: 0,
            lx: 206,
            ly: 126,
            px: 114,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [640, 0],
                interval: normal_a
            },
            {
                rect: [1280, 0],
                interval: normal_a
            },
            {
                rect: [1920, 0],
                interval: normal_a
            },
            {
                rect: [2560, 0],
                interval: normal_a
            },
            {
                rect: [0, 320],
                interval: normal_a
            },
            {
                rect: [640, 320],
                interval: normal_a
            },
            {
                rect: [1280, 320],
                interval: normal_a
            },
            ]
        },
        207 : {
            id: 207,
            herotype:2,
            width: 360,
            height: 180,
            mx: -102,
            my: 0,
            attackt: normal_a * 8,
            rx: -140,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [360, 0],
                interval: normal_a
            },
            {
                rect: [720, 0],
                interval: normal_a
            },
            {
                rect: [1080, 0],
                interval: normal_a
            },
            {
                rect: [1440, 0],
                interval: normal_a
            },
            {
                rect: [0, 180],
                interval: normal_a
            },
            {
                rect: [360, 180],
                interval: normal_a
            },
            {
                rect: [720, 180],
                interval: normal_a
            },
            ]
        },
        208 : {
            id: 208,
            herotype: 2,
            width: 524,
            height: 262,
            mx: -142,
            my: 10,
            attackt: normal_a * 8,
            rx: -230,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 68,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [524, 0],
                interval: normal_a
            },
            {
                rect: [1048, 0],
                interval: normal_a
            },
            {
                rect: [1572, 0],
                interval: normal_a
            },
            {
                rect: [2096, 0],
                interval: normal_a
            },
            {
                rect: [0, 262],
                interval: normal_a
            },
            {
                rect: [524, 262],
                interval: normal_a
            },
            {
                rect: [1048, 262],
                interval: normal_a
            },
            ]
        },
        209 : {
            id: 209,
            herotype: 2,
            width: 640,
            height: 320,
            mx: -188,
            my: 20,
            attackt: normal_a * 8,
            rx: -262,
            sx: 138,
            sy: 0,
            bx: 154,
            by: 0,
            lx: 206,
            ly: 126,
            px: 114,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [640, 0],
                interval: normal_a
            },
            {
                rect: [1280, 0],
                interval: normal_a
            },
            {
                rect: [1920, 0],
                interval: normal_a
            },
            {
                rect: [2560, 0],
                interval: normal_a
            },
            {
                rect: [0, 320],
                interval: normal_a
            },
            {
                rect: [640, 320],
                interval: normal_a
            },
            {
                rect: [1280, 320],
                interval: normal_a
            },
            ]
        },
        210 : {
            id: 210,
            herotype:2,
            width: 360,
            height: 180,
            mx: -102,
            my: 20,
            attackt: normal_a * 8,
            rx: -150,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 28,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [360, 0],
                interval: normal_a
            },
            {
                rect: [720, 0],
                interval: normal_a
            },
            {
                rect: [1080, 0],
                interval: normal_a
            },
            {
                rect: [1440, 0],
                interval: normal_a
            },
            {
                rect: [0, 180],
                interval: normal_a
            },
            {
                rect: [360, 180],
                interval: normal_a
            },
            {
                rect: [720, 180],
                interval: normal_a
            },
            ]
        },
        211 : {
            id: 211,
            herotype: 2,
            width: 524,
            height: 262,
            mx: -142,
            my: 24,
            attackt: normal_a * 8,
            rx: -230,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 68,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [524, 0],
                interval: normal_a
            },
            {
                rect: [1048, 0],
                interval: normal_a
            },
            {
                rect: [1572, 0],
                interval: normal_a
            },
            {
                rect: [2096, 0],
                interval: normal_a
            },
            {
                rect: [0, 262],
                interval: normal_a
            },
            {
                rect: [524, 262],
                interval: normal_a
            },
            {
                rect: [1048, 262],
                interval: normal_a
            },
            ]
        },
        212 : {
            id: 212,
            herotype: 2,
            width: 640,
            height: 320,
            mx: -188,
            my: 30,
            attackt: normal_a * 8,
            rx: -300,
            sx: 138,
            sy: 0,
            bx: 154,
            by: 0,
            lx: 206,
            ly: 126,
            px: 114,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [640, 0],
                interval: normal_a
            },
            {
                rect: [1280, 0],
                interval: normal_a
            },
            {
                rect: [1920, 0],
                interval: normal_a
            },
            {
                rect: [2560, 0],
                interval: normal_a
            },
            {
                rect: [0, 320],
                interval: normal_a
            },
            {
                rect: [640, 320],
                interval: normal_a
            },
            {
                rect: [1280, 320],
                interval: normal_a
            },
            ]
        },
        213 : {
            id: 213,
            herotype:2,
            width: 360,
            height: 180,
            mx: -127,
            my: 10,
            rx: -100,
            sx: 77,
            sy: 0,
            bx: 93,
            by: 0,
            lx: 145,
            ly: 126,
            px: 65,
            attackt: normal_a * 8,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [360, 0],
                interval: normal_a
            },
            {
                rect: [720, 0],
                interval: normal_a
            },
            {
                rect: [1080, 0],
                interval: normal_a
            },
            {
                rect: [1440, 0],
                interval: normal_a
            },
            {
                rect: [0, 180],
                interval: normal_a
            },
            {
                rect: [360, 180],
                interval: normal_a
            },
            {
                rect: [720, 180],
                interval: normal_a
            },
            ]
        },
        214 : {
            id: 214,
            herotype: 2,
            width: 524,
            height: 262,
            mx: -204,
            my: 32,
            rx: -106,
            sx: 154,
            sy: 0,
            bx: 170,
            by: 0,
            lx: 222,
            ly: 126,
            px: 119,
            attackt: normal_a * 8,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [524, 0],
                interval: normal_a
            },
            {
                rect: [1048, 0],
                interval: normal_a
            },
            {
                rect: [1572, 0],
                interval: normal_a
            },
            {
                rect: [2096, 0],
                interval: normal_a
            },
            {
                rect: [0, 262],
                interval: normal_a
            },
            {
                rect: [524, 262],
                interval: normal_a
            },
            {
                rect: [1048, 262],
                interval: normal_a
            },
            ]
        },
        215 : {
            id: 215,
            herotype: 2,
            width: 640,
            height: 320,
            mx: -236,
            my: 28,
            rx: -204,
            sx: 186,
            sy: 0,
            bx: 202,
            by: 0,
            lx: 254,
            ly: 126,
            px: 162,
            attackt: normal_a * 8,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [640, 0],
                interval: normal_a
            },
            {
                rect: [1280, 0],
                interval: normal_a
            },
            {
                rect: [1920, 0],
                interval: normal_a
            },
            {
                rect: [2560, 0],
                interval: normal_a
            },
            {
                rect: [0, 320],
                interval: normal_a
            },
            {
                rect: [640, 320],
                interval: normal_a
            },
            {
                rect: [1280, 320],
                interval: normal_a
            },
            ]
        },
        301 : {
            id: 301,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -74,
            my: 4,
            rx: -166,
            sx: 24,
            sy: 0,
            bx: 40,
            by: 0,
            lx: 92,
            ly: 126,
            px: 0,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        302 : {
            id: 302,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -138,
            my: 4,
            rx: -38,
            sx: 88,
            sy: 0,
            bx: 104,
            by: 0,
            lx: 156,
            ly: 126,
            px: 64,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        303 : {
            id: 303,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -82,
            my: 8,
            rx: -150,
            sx: 32,
            sy: 0,
            bx: 48,
            by: 0,
            lx: 100,
            ly: 126,
            px: 8,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        304 : {
            id: 304,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -134,
            my: 8,
            rx: -46,
            sx: 84,
            sy: 0,
            bx: 100,
            by: 0,
            lx: 152,
            ly: 126,
            px: 60,
            ax: -10,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        305 : {
            id: 305,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -106,
            my: 2,
            rx: -102,
            sx: 56,
            sy: 0,
            bx: 72,
            by: 0,
            lx: 124,
            ly: 126,
            px: 32,
            ax: -10,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        306 : {
            id: 306,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -80,
            my: 8,
            rx: -154,
            sx: 30,
            sy: 0,
            bx: 46,
            by: 0,
            lx: 98,
            ly: 126,
            px: 6,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        307 : {
            id: 307,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -80,
            my: 8,
            rx: -154,
            sx: 30,
            sy: 0,
            bx: 46,
            by: 0,
            lx: 98,
            ly: 126,
            px: 6,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        308 : {
            id: 308,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -104,
            my: 8,
            rx: -106,
            sx: 54,
            sy: 0,
            bx: 70,
            by: 0,
            lx: 122,
            ly: 126,
            px: 30,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        309 : {
            id: 309,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -86,
            my: 10,
            rx: -142,
            sx: 36,
            sy: 0,
            bx: 52,
            by: 0,
            lx: 104,
            ly: 126,
            px: 12,
            ax: -50,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        310 : {
            id: 310,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -112,
            my: 10,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 38,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        311 : {
            id: 311,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -112,
            my: 12,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 38,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        312 : {
            id: 312,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -90,
            my: 10,
            rx: -134,
            sx: 40,
            sy: 0,
            bx: 56,
            by: 0,
            lx: 108,
            ly: 126,
            px: 16,
            ax: -50,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        313 : {
            id: 313,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -116,
            my: 14,
            rx: -82,
            sx: 66,
            sy: 0,
            bx: 82,
            by: 0,
            lx: 134,
            ly: 126,
            px: 42,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        314 : {
            id: 314,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -116,
            my: 10,
            rx: -82,
            sx: 66,
            sy: 0,
            bx: 82,
            by: 0,
            lx: 134,
            ly: 126,
            px: 42,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        315 : {
            id: 315,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -114,
            my: 18,
            rx: -86,
            sx: 64,
            sy: 0,
            bx: 80,
            by: 0,
            lx: 132,
            ly: 126,
            px: 40,
            ax: -50,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        316 : {
            id: 316,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -110,
            my: 0,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 36,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        317 : {
            id: 317,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -110,
            my: 10,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 36,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        318 : {
            id: 318,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -130,
            my: 10,
            rx: -54,
            sx: 80,
            sy: 0,
            bx: 96,
            by: 0,
            lx: 148,
            ly: 126,
            px: 56,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        319 : {
            id: 319,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -100,
            my: 4,
            rx: -114,
            sx: 50,
            sy: 0,
            bx: 66,
            by: 0,
            lx: 118,
            ly: 126,
            px: 26,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        320 : {
            id: 320,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -152,
            my: 0,
            rx: -10,
            sx: 102,
            sy: 0,
            bx: 118,
            by: 0,
            lx: 170,
            ly: 126,
            px: 78,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        321 : {
            id: 321,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -100,
            my: 0,
            rx: -114,
            sx: 50,
            sy: 0,
            bx: 66,
            by: 0,
            lx: 118,
            ly: 126,
            px: 26,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        322 : {
            id: 322,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -80,
            my: 6,
            rx: -154,
            sx: 30,
            sy: 0,
            bx: 46,
            by: 0,
            lx: 98,
            ly: 126,
            px: 6,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        323 : {
            id: 323,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -140,
            my: 6,
            rx: -34,
            sx: 90,
            sy: 0,
            bx: 106,
            by: 0,
            lx: 158,
            ly: 126,
            px: 66,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        324 : {
            id: 324,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -130,
            my: 6,
            rx: -54,
            sx: 80,
            sy: 0,
            bx: 96,
            by: 0,
            lx: 148,
            ly: 126,
            px: 56,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        325 : {
            id: 325,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -120,
            my: 6,
            rx: -74,
            sx: 70,
            sy: 0,
            bx: 86,
            by: 0,
            lx: 138,
            ly: 126,
            px: 46,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        326 : {
            id: 326,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -94,
            my: 6,
            rx: -126,
            sx: 44,
            sy: 0,
            bx: 60,
            by: 0,
            lx: 112,
            ly: 126,
            px: 20,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        327 : {
            id: 327,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -154,
            my: 16,
            rx: -6,
            sx: 104,
            sy: 0,
            bx: 120,
            by: 0,
            lx: 172,
            ly: 126,
            px: 80,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        328 : {
            id: 328,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -154,
            my: 10,
            rx: -6,
            sx: 104,
            sy: 0,
            bx: 120,
            by: 0,
            lx: 172,
            ly: 126,
            px: 80,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        329 : {
            id: 329,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -104,
            my: 10,
            rx: -106,
            sx: 54,
            sy: 0,
            bx: 70,
            by: 0,
            lx: 122,
            ly: 126,
            px: 30,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        330 : {
            id: 330,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -132,
            my: 0,
            rx: -50,
            sx: 82,
            sy: 0,
            bx: 98,
            by: 0,
            lx: 150,
            ly: 126,
            px: 58,
            ax: 0,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        331 : {
            id: 331,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -116,
            my: 0,
            rx: -82,
            sx: 66,
            sy: 0,
            bx: 82,
            by: 0,
            lx: 134,
            ly: 126,
            px: 42,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        332 : {
            id: 332,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -92,
            my: 6,
            rx: -130,
            sx: 42,
            sy: 0,
            bx: 58,
            by: 0,
            lx: 110,
            ly: 126,
            px: 18,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        333 : {
            id: 333,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -132,
            my: 8,
            rx: -50,
            sx: 82,
            sy: 0,
            bx: 98,
            by: 0,
            lx: 150,
            ly: 126,
            px: 58,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        334 : {
            id: 334,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -108,
            my: 8,
            rx: -98,
            sx: 58,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 126,
            ly: 126,
            px: 34,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        335 : {
            id: 335,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -92,
            my: 0,
            rx: -130,
            sx: 42,
            sy: 0,
            bx: 58,
            by: 0,
            lx: 110,
            ly: 126,
            px: 18,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        336 : {
            id: 336,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -108,
            my: 6,
            rx: -98,
            sx: 58,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 126,
            ly: 126,
            px: 34,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        337 : {
            id: 337,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -126,
            my: 14,
            rx: -62,
            sx: 76,
            sy: 0,
            bx: 92,
            by: 0,
            lx: 144,
            ly: 126,
            px: 52,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        338 : {
            id: 338,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -126,
            my: 8,
            rx: -62,
            sx: 76,
            sy: 0,
            bx: 92,
            by: 0,
            lx: 144,
            ly: 126,
            px: 52,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        339 : {
            id: 339,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -94,
            my: 14,
            rx: -126,
            sx: 44,
            sy: 0,
            bx: 60,
            by: 0,
            lx: 112,
            ly: 126,
            px: 20,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        340 : {
            id: 340,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -88,
            my: -2,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 14,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        341 : {
            id: 341,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -108,
            my: 8,
            rx: -98,
            sx: 58,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 126,
            ly: 126,
            px: 34,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            ]
        },
        342 : {
            id: 342,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -108,
            my: 10,
            rx: -98,
            sx: 58,
            sy: 0,
            bx: 74,
            by: 0,
            lx: 126,
            ly: 126,
            px: 34,
            ax: -50,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            }
            ]
        },
        343 : {
            id: 343,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -118,
            my: 25,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 44,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            }
            ]
        },
        344 : {
            id: 344,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -98,
            my: 10,
            rx: -118,
            sx: 48,
            sy: 0,
            bx: 64,
            by: 0,
            lx: 116,
            ly: 126,
            px: 24,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            }
            ]
        },
        345 : {
            id: 345,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 7,
            mx: -75,
            my: 10,
            rx: -164,
            sx: 25,
            sy: 0,
            bx: 41,
            by: 0,
            lx: 93,
            ly: 126,
            px: 1,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            }
            ]
        },
        346 : {
            id: 346,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 6,
            mx: -136,
            my: 20,
            rx: -42,
            sx: 86,
            sy: 0,
            bx: 102,
            by: 0,
            lx: 154,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            ]
        },
        347 : {
            id: 347,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -94,
            my: 20,
            rx: -126,
            sx: 44,
            sy: 0,
            bx: 60,
            by: 0,
            lx: 112,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        348 : {
            id: 348,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -122,
            my: 27,
            rx: -70,
            sx: 72,
            sy: 0,
            bx: 88,
            by: 0,
            lx: 140,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        349 : {
            id: 349,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -86,
            my: 41,
            rx: -142,
            sx: 36,
            sy: 0,
            bx: 52,
            by: 0,
            lx: 104,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        350 : {
            id: 350,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -83,
            my: 20,
            rx: -148,
            sx: 33,
            sy: 0,
            bx: 49,
            by: 0,
            lx: 101,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        351 : {
            id: 351,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -110,
            my: 10,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        352 : {
            id: 352,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -144,
            my: 27,
            rx: -26,
            sx: 94,
            sy: 0,
            bx: 110,
            by: 0,
            lx: 162,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        353 : {
            id: 353,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -124,
            my: 12,
            rx: -66,
            sx: 74,
            sy: 0,
            bx: 90,
            by: 0,
            lx: 142,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        354 : {
            id: 354,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -135,
            my: 32,
            rx: -44,
            sx: 85,
            sy: 0,
            bx: 101,
            by: 0,
            lx: 153,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        355 : {
            id: 355,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -91,
            my: 2,
            rx: -132,
            sx: 41,
            sy: 0,
            bx: 57,
            by: 0,
            lx: 109,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        356 : {
            id: 356,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -110,
            my: 22,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        357 : {
            id: 357,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -96,
            my: 15,
            rx: -122,
            sx: 46,
            sy: 0,
            bx: 62,
            by: 0,
            lx: 114,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        358 : {
            id: 358,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -126,
            my: 12,
            rx: -62,
            sx: 76,
            sy: 0,
            bx: 92,
            by: 0,
            lx: 144,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        359 : {
            id: 359,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -122,
            my: 13,
            rx: -70,
            sx: 72,
            sy: 0,
            bx: 88,
            by: 0,
            lx: 140,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        360 : {
            id: 360,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -68,
            my: 4,
            rx: -178,
            sx: 18,
            sy: 0,
            bx: 34,
            by: 0,
            lx: 86,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        361 : {
            id: 361,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -103,
            my: 6,
            rx: -108,
            sx: 53,
            sy: 0,
            bx: 69,
            by: 0,
            lx: 121,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        362 : {
            id: 362,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -123,
            my: 16,
            rx: -68,
            sx: 73,
            sy: 0,
            bx: 89,
            by: 0,
            lx: 141,
            ly: 126,

            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        363 : {
            id: 363,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -136,
            my: 28,
            rx: -42,
            sx: 86,
            sy: 0,
            bx: 102,
            by: 0,
            lx: 154,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        364 : {
            id: 364,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -88,
            my: 19,
            rx: -138,
            sx: 38,
            sy: 0,
            bx: 54,
            by: 0,
            lx: 106,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        365 : {
            id: 365,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -79,
            my: 32,
            rx: -156,
            sx: 29,
            sy: 0,
            bx: 45,
            by: 0,
            lx: 97,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        366 : {
            id: 366,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -104,
            my: 18,
            rx: -106,
            sx: 54,
            sy: 0,
            bx: 70,
            by: 0,
            lx: 122,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        367 : {
            id: 367,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -106,
            my: 12,
            rx: -102,
            sx: 56,
            sy: 0,
            bx: 72,
            by: 0,
            lx: 124,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        368 : {
            id: 368,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -118,
            my: 26,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        369 : {
            id: 369,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -91,
            my: 19,
            rx: -132,
            sx: 41,
            sy: 0,
            bx: 57,
            by: 0,
            lx: 109,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        370 : {
            id: 370,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -92,
            my: 19,
            rx: -130,
            sx: 42,
            sy: 0,
            bx: 58,
            by: 0,
            lx: 110,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        371 : {
            id: 371,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -147,
            my: 16,
            rx: -20,
            sx: 97,
            sy: 0,
            bx: 113,
            by: 0,
            lx: 165,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        372 : {
            id: 372,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -110,
            my: 15,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        373 : {
            id: 373,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -78,
            my: 10,
            rx: -158,
            sx: 28,
            sy: 0,
            bx: 44,
            by: 0,
            lx: 96,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        374 : {
            id: 374,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -82,
            my: 14,
            rx: -150,
            sx: 32,
            sy: 0,
            bx: 48,
            by: 0,
            lx: 100,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        375 : {
            id: 375,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -102,
            my: 13,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        376 : {
            id: 376,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -102,
            my: 22,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        377 : {
            id: 377,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -147,
            my: 22,
            rx: -20,
            sx: 97,
            sy: 0,
            bx: 113,
            by: 0,
            lx: 165,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        378 : {
            id: 378,
            herotype:1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -144,
            my: 31,
            rx: -26,
            sx: 94,
            sy: 0,
            bx: 110,
            by: 0,
            lx: 162,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        379 : {
            id: 379,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -126,
            my: 16,
            rx: -62,
            sx: 76,
            sy: 0,
            bx: 92,
            by: 0,
            lx: 144,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        380 : {
            id: 380,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -137,
            my: 9,
            rx: -40,
            sx: 87,
            sy: 0,
            bx: 103,
            by: 0,
            lx: 155,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        381 : {
            id: 381,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -87,
            my: 16,
            rx: -140,
            sx: 37,
            sy: 0,
            bx: 53,
            by: 0,
            lx: 105,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        382 : {
            id: 382,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -133,
            my: 31,
            rx: -48,
            sx: 83,
            sy: 0,
            bx: 99,
            by: 0,
            lx: 151,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        383 : {
            id: 383,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -100,
            my: 29,
            rx: -114,
            sx: 50,
            sy: 0,
            bx: 66,
            by: 0,
            lx: 118,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        384 : {
            id: 384,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -137,
            my: 36,
            rx: -40,
            sx: 87,
            sy: 0,
            bx: 103,
            by: 0,
            lx: 155,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        385 : {
            id: 385,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -107,
            my: 15,
            rx: -100,
            sx: 57,
            sy: 0,
            bx: 73,
            by: 0,
            lx: 125,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        386 : {
            id: 386,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -142,
            my: 18,
            rx: -30,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        387 : {
            id: 387,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -100,
            my: 22,
            rx: -114,
            sx: 50,
            sy: 0,
            bx: 66,
            by: 0,
            lx: 118,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        388 : {
            id: 388,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -130,
            my: 15,
            rx: -54,
            sx: 80,
            sy: 0,
            bx: 96,
            by: 0,
            lx: 148,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        389 : {
            id: 389,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -117,
            my: 25,
            rx: -80,
            sx: 67,
            sy: 0,
            bx: 83,
            by: 0,
            lx: 135,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        390 : {
            id: 390,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -117,
            my: 20,
            rx: -80,
            sx: 67,
            sy: 0,
            bx: 83,
            by: 0,
            lx: 135,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        391 : {
            id: 391,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -125,
            my: 18,
            rx: -64,
            sx: 75,
            sy: 0,
            bx: 91,
            by: 0,
            lx: 143,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        392 : {
            id: 392,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -111,
            my: 14,
            rx: -92,
            sx: 61,
            sy: 0,
            bx: 77,
            by: 0,
            lx: 129,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        393 : {
            id: 393,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -117,
            my: 31,
            rx: -80,
            sx: 67,
            sy: 0,
            bx: 83,
            by: 0,
            lx: 135,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        394 : {
            id: 394,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -139,
            my: 26,
            rx: -36,
            sx: 89,
            sy: 0,
            bx: 105,
            by: 0,
            lx: 157,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        395 : {
            id: 395,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -95,
            my: 25,
            rx: -124,
            sx: 45,
            sy: 0,
            bx: 61,
            by: 0,
            lx: 113,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        396 : {
            id: 396,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -129,
            my: 32,
            rx: -56,
            sx: 79,
            sy: 0,
            bx: 95,
            by: 0,
            lx: 147,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        397 : {
            id: 397,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -132,
            my: 21,
            rx: -50,
            sx: 82,
            sy: 0,
            bx: 98,
            by: 0,
            lx: 150,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        398 : {
            id: 398,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -111,
            my: 27,
            rx: -92,
            sx: 61,
            sy: 0,
            bx: 77,
            by: 0,
            lx: 129,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        399 : {
            id: 399,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -112,
            my: 31,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        400 : {
            id: 400,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -110,
            my: 18,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        401 : {
            id: 401,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -116,
            my: 31,
            rx: -82,
            sx: 66,
            sy: 0,
            bx: 82,
            by: 0,
            lx: 134,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        402 : {
            id: 402,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -93,
            my: 6,
            rx: -128,
            sx: 43,
            sy: 0,
            bx: 59,
            by: 0,
            lx: 111,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        403 : {
            id: 403,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -93,
            my: 26,
            rx: -128,
            sx: 43,
            sy: 0,
            bx: 59,
            by: 0,
            lx: 111,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        404 : {
            id: 404,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -110,
            my: 16,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        405 : {
            id: 405,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -110,
            my: 32,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        406 : {
            id: 406,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -103,
            my: 20,
            rx: -108,
            sx: 53,
            sy: 0,
            bx: 69,
            by: 0,
            lx: 121,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        407 : {
            id: 407,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -104,
            my: 32,
            rx: -106,
            sx: 54,
            sy: 0,
            bx: 70,
            by: 0,
            lx: 122,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        408 : {
            id: 408,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -112,
            my: 37,
            rx: -90,
            sx: 62,
            sy: 0,
            bx: 78,
            by: 0,
            lx: 130,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        409 : {
            id: 409,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -100,
            my: 18,
            rx: -114,
            sx: 50,
            sy: 0,
            bx: 66,
            by: 0,
            lx: 118,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        410 : {
            id: 410,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -135,
            my: 23,
            rx: -44,
            sx: 85,
            sy: 0,
            bx: 101,
            by: 0,
            lx: 153,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        411 : {
            id: 411,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -120,
            my: 27,
            rx: -74,
            sx: 70,
            sy: 0,
            bx: 86,
            by: 0,
            lx: 138,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        412 : {
            id: 412,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -125,
            my: 20,
            rx: -64,
            sx: 75,
            sy: 0,
            bx: 91,
            by: 0,
            lx: 143,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        413 : {
            id: 413,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -109,
            my: 12,
            rx: -96,
            sx: 59,
            sy: 0,
            bx: 75,
            by: 0,
            lx: 127,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        414 : {
            id: 414,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -130,
            my: 15,
            rx: -54,
            sx: 80,
            sy: 0,
            bx: 96,
            by: 0,
            lx: 148,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            {
                rect: [945, 253],
                interval: normal_a
            },
            ]
        },
        415 : {
            id: 415,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -107,
            my: 11,
            rx: -100,
            sx: 57,
            sy: 0,
            bx: 73,
            by: 0,
            lx: 125,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        416 : {
            id: 416,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -120,
            my: 24,
            rx: -74,
            sx: 70,
            sy: 0,
            bx: 86,
            by: 0,
            lx: 138,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        417 : {
            id: 417,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -107,
            my: 8,
            rx: -100,
            sx: 57,
            sy: 0,
            bx: 73,
            by: 0,
            lx: 125,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        418 : {
            id: 418,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -117,
            my: 6,
            rx: -80,
            sx: 67,
            sy: 0,
            bx: 83,
            by: 0,
            lx: 135,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        419 : {
            id: 419,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -107,
            my: 24,
            rx: -100,
            sx: 57,
            sy: 0,
            bx: 73,
            by: 0,
            lx: 125,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        420 : {
            id: 420,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -91,
            my: 22,
            rx: -132,
            sx: 41,
            sy: 0,
            bx: 57,
            by: 0,
            lx: 109,
            ly: 126,
            px: 62,
            ax: 10,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        421 : {
            id: 421,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -116,
            my: 18,
            rx: -82,
            sx: 66,
            sy: 0,
            bx: 82,
            by: 0,
            lx: 134,
            ly: 126,
            px: 62,
            ax: 10,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        422 : {
            id: 422,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -72,
            my: 21,
            rx: -170,
            sx: 22,
            sy: 0,
            bx: 38,
            by: 0,
            lx: 90,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        423 : {
            id: 423,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -118,
            my: 19,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 62,
            ax: 10,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        424 : {
            id: 424,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -79,
            my: 14,
            rx: -156,
            sx: 29,
            sy: 0,
            bx: 45,
            by: 0,
            lx: 97,
            ly: 126,
            px: 62,
            ax: -30,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        425 : {
            id: 425,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -152,
            my: 24,
            rx: -10,
            sx: 102,
            sy: 0,
            bx: 118,
            by: 0,
            lx: 170,
            ly: 126,
            px: 62,
            ax: 10,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        426 : {
            id: 426,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -87,
            my: 31,
            rx: -140,
            sx: 37,
            sy: 0,
            bx: 53,
            by: 0,
            lx: 105,
            ly: 126,
            px: 62,
            ax: 10,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        427 : {
            id: 427,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -102,
            my: 27,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        428 : {
            id: 428,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -102,
            my: 0,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 62,
            ax: -40,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        429 : {
            id: 429,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -102,
            my: 11,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        430 : {
            id: 430,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -102,
            my: 23,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        431 : {
            id: 431,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -134,
            my: 30,
            rx: -46,
            sx: 84,
            sy: 0,
            bx: 100,
            by: 0,
            lx: 152,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        432 : {
            id: 432,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -130,
            my: 8,
            rx: -54,
            sx: 80,
            sy: 0,
            bx: 96,
            by: 0,
            lx: 148,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        433 : {
            id: 433,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -85,
            my: 2,
            rx: -144,
            sx: 35,
            sy: 0,
            bx: 51,
            by: 0,
            lx: 103,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        434 : {
            id: 434,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -95,
            my: 2,
            rx: -124,
            sx: 45,
            sy: 0,
            bx: 61,
            by: 0,
            lx: 113,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        435 : {
            id: 435,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -95,
            my: 23,
            rx: -124,
            sx: 45,
            sy: 0,
            bx: 61,
            by: 0,
            lx: 113,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        436 : {
            id: 436,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -92,
            my: 13,
            rx: -130,
            sx: 42,
            sy: 0,
            bx: 58,
            by: 0,
            lx: 110,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        437 : {
            id: 437,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -128,
            my: 13,
            rx: -58,
            sx: 78,
            sy: 0,
            bx: 94,
            by: 0,
            lx: 146,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        438 : {
            id: 438,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -149,
            my: 21,
            rx: -16,
            sx: 99,
            sy: 0,
            bx: 115,
            by: 0,
            lx: 167,
            ly: 126,
            px: 62,
            ax: 20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        439 : {
            id: 439,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -87,
            my: 9,
            rx: -140,
            sx: 37,
            sy: 0,
            bx: 53,
            by: 0,
            lx: 105,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        440 : {
            id: 440,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -116,
            my: 16,
            rx: -82,
            sx: 66,
            sy: 0,
            bx: 82,
            by: 0,
            lx: 134,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        441 : {
            id: 441,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -164,
            my: 29,
            rx: 14,
            sx: 114,
            sy: 0,
            bx: 130,
            by: 0,
            lx: 182,
            ly: 126,
            px: 62,
            ax: 20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        442 : {
            id: 442,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -102,
            my: 6,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        443 : {
            id: 443,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -117,
            my: 19,
            rx: -80,
            sx: 67,
            sy: 0,
            bx: 83,
            by: 0,
            lx: 135,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        444 : {
            id: 444,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -140,
            my: 39,
            rx: -34,
            sx: 90,
            sy: 0,
            bx: 106,
            by: 0,
            lx: 158,
            ly: 126,
            px: 62,
            ax: 20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        445 : {
            id: 445,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -133,
            my: 26,
            rx: -48,
            sx: 83,
            sy: 0,
            bx: 99,
            by: 0,
            lx: 151,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        446 : {
            id: 446,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -128,
            my: 10,
            rx: -58,
            sx: 78,
            sy: 0,
            bx: 94,
            by: 0,
            lx: 146,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        447 : {
            id: 447,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -103,
            my: 21,
            rx: -108,
            sx: 53,
            sy: 0,
            bx: 69,
            by: 0,
            lx: 121,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        448 : {
            id: 448,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -157,
            my: 24,
            rx: 0,
            sx: 107,
            sy: 0,
            bx: 123,
            by: 0,
            lx: 175,
            ly: 126,
            px: 62,
            ax: 20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        449 : {
            id: 449,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -97,
            my: 30,
            rx: -120,
            sx: 47,
            sy: 0,
            bx: 63,
            by: 0,
            lx: 115,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        450 : {
            id: 450,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -80,
            my: 27,
            rx: -154,
            sx: 30,
            sy: 0,
            bx: 46,
            by: 0,
            lx: 98,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        451 : {
            id: 451,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -90,
            my: 21,
            rx: -134,
            sx: 40,
            sy: 0,
            bx: 56,
            by: 0,
            lx: 108,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        452 : {
            id: 452,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -142,
            my: 11,
            rx: -30,
            sx: 92,
            sy: 0,
            bx: 108,
            by: 0,
            lx: 160,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        453 : {
            id: 453,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -99,
            my: 17,
            rx: -116,
            sx: 49,
            sy: 0,
            bx: 65,
            by: 0,
            lx: 117,
            ly: 126,
            px: 62,
            ax: -80,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        454 : {
            id: 454,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -107,
            my: 2,
            rx: -100,
            sx: 57,
            sy: 0,
            bx: 73,
            by: 0,
            lx: 125,
            ly: 126,
            px: 67,
            ax: -60,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        455 : {
            id: 455,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -127,
            my: 10,
            rx: -60,
            sx: 77,
            sy: 0,
            bx: 93,
            by: 0,
            lx: 145,
            ly: 126,
            px: 54,
            ax: -60,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        456 : {
            id: 456,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -122,
            my: 27,
            rx: -70,
            sx: 72,
            sy: 0,
            bx: 88,
            by: 0,
            lx: 140,
            ly: 126,
            px: 62,
            ax: -20,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        457 : {
            id: 457,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -92,
            my: 48,
            rx: -130,
            sx: 42,
            sy: 0,
            bx: 58,
            by: 0,
            lx: 110,
            ly: 126,
            px: 58,
            ax: -80,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        458 : {
            id: 458,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
           mx: -131,
            my: 33,
            rx: -52,
            sx: 81,
            sy: 0,
            bx: 97,
            by: 0,
            lx: 149,
            ly: 126,
            px: 57,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        459 : {              
            id: 459,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -118,
            my: 16,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 60,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        460 : {
            id: 460,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -118,
            my: 16,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 62,
            ax: -80, 
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        461 : {
            id: 461,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -118,
            my: 7,
            rx: -78,
            sx: 68,
            sy: 0,
            bx: 84,
            by: 0,
            lx: 136,
            ly: 126,
            px: 62,
            ax: -20,   
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        462 : {
            id: 462,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -102,
            my: 7,
            rx: -110,
            sx: 52,
            sy: 0,
            bx: 68,
            by: 0,
            lx: 120,
            ly: 126,
            px: 62,
            ax: -20, 
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        463 : {
            id: 463,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -127,
            my: 10,
            rx: -60,
            sx: 77,
            sy: 0,
            bx: 93,
            by: 0,
            lx: 145,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        464 : {
            id: 464,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -106,
            my: 11,
            rx: -102,
            sx: 56,
            sy: 0,
            bx: 72,
            by: 0,
            lx: 124,
            ly: 126,
            px: 62,
            ax: -20,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        465 : {
            id: 465,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -124,
            my: 24,
            rx: -66,
            sx: 74,
            sy: 0,
            bx: 90,
            by: 0,
            lx: 142,
            ly: 126,
            px: 62,
            ax: -20,
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        466 : {
            id: 466,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -124,
            my: 18,
            rx: -66,
            sx: 74,
            sy: 0,
            bx: 90,
            by: 0,
            lx: 142,
            ly: 126,
            px: 62,
            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        467 : {
            id: 467,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -97,
            my: 6,
            rx: -120,
            sx: 47,
            sy: 0,
            bx: 63,
            by: 0,
            lx: 115,
            ly: 126,
            px: 35,
            ax: -80,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        468 : {
            id: 468,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -101,
            my: 9,
            rx: -112,
            sx: 51,
            sy: 0,
            bx: 67,
            by: 0,
            lx: 119,
            ly: 126,
            px: 39,
            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        469 : {
            id: 469,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -87,
            my: 47,
            rx: -140,
            sx: 37,
            sy: 0,
            bx: 53,
            by: 0,
            lx: 105,
            ly: 126,
            px: 25,
            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        470 : {
            id: 470,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -98,
            my: 7,
            rx: -118,
            sx: 48,
            sy: 0,
            bx: 64,
            by: 0,
            lx: 116,
            ly: 126,
            px: 36,
            ax: -90,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        471 : {
            id: 471,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -110,
            my: 7,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 48,
            ax: -30,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        472 : {
            id: 472,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -98,
            my: 7,
            rx: -118,
            sx: 48,
            sy: 0,
            bx: 64,
            by: 0,
            lx: 116,
            ly: 126,
            px: 36,
            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        473 : {
            id: 473,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -140,
            my: 28,
            rx: -34,
            sx: 90,
            sy: 0,
            bx: 106,
            by: 0,
            lx: 158,
            ly: 126,
            px: 78,

            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        474 : {
            id: 474,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -79,
            my: 7,
            rx: -156,
            sx: 29,
            sy: 0,
            bx: 45,
            by: 0,
            lx: 97,
            ly: 126,
            px: 17,
            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        475 : {
            id: 475,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -96,
            my: 5,
            rx: -122,
            sx: 46,
            sy: 0,
            bx: 62,
            by: 0,
            lx: 114,
            ly: 126,
            px: 34,
            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        476 : {
            id: 476,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -131,
            my: 7,
            rx: -52,
            sx: 81,
            sy: 0,
            bx: 97,
            by: 0,
            lx: 149,
            ly: 126,
            px: 69,
            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        477 : {
            id: 477,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -140,
            my: 20,
            rx: -34,
            sx: 90,
            sy: 0,
            bx: 106,
            by: 0,
            lx: 158,
            ly: 126,
            px: 78,
            ax: -40,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        478 : {
            id: 478,
            herotype: 1,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -128,
            my: 4,
            rx: -58,
            sx: 78,
            sy: 0,
            bx: 94,
            by: 0,
            lx: 146,
            ly: 126,
            px: 66,
            ax: -40,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        479 : {
            id: 479,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -110,
            my: 6,
            rx: -94,
            sx: 60,
            sy: 0,
            bx: 76,
            by: 0,
            lx: 128,
            ly: 126,
            px: 48,
            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
        480 : {
            id: 480,
            herotype: 2,
            width: 315,
            height: 253,
            attackt: normal_a * 8,
            mx: -92,
            my: 3,
            rx: -130,
            sx: 42,
            sy: 0,
            bx: 58,
            by: 0,
            lx: 110,
            ly: 126,
            px: 30,
            ax: -60,  
            animations2: [{
                rect: [0, 0],
                label: "attack",
                interval: normal_a
            },
            {
                rect: [315, 0],
                interval: normal_a
            },
            {
                rect: [630, 0],
                interval: normal_a
            },
            {
                rect: [945, 0],
                interval: normal_a
            },
            {
                rect: [1260, 0],
                interval: normal_a
            },
            {
                rect: [0, 253],
                interval: normal_a
            },
            {
                rect: [315, 253],
                interval: normal_a
            },
            {
                rect: [630, 253],
                interval: normal_a
            },
            ]
        },
    };

})();