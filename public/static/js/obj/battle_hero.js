/* 武将 
hero = new Hero({id:'hero1', herodata: herodata, pos:1, team:'left', heroimage:dom});
*/
var nowHero = {}; //用于战斗保存当前操作对象

var Hero = function(props) {
    this.e = null; //元素
    this.ini = {};
    this.heroid = 0; //id
    this.name = '';
    this.pos = 0; //位置
    this.team = 'left'; //左边or右边
    this.herodata = null; //数据 当前血量等
    this.heroimage = null; //图片url
    this.body = null; //人物身体动画
    this.zindex = 0;
    this.timer = null;
    this.timeout = [];
    this.isApparentDeath = false; // 是否是假死，假死时，即使血量为0也不死
    this.passiveSkillAnim = null; // 神诸葛的被动技能动画

    this.allblood = 0;
    this.blood = 0;
    this.power = 0; //士气(100max)
    this.poweranim = null;

    this.attackanim = null;
    this.faintDom = null; //眩晕dom

    this.shieldRound = 0;
    this.penetrate = null;
    this.penetrateRound = 0;
    this.attackForce = null;
    this.attackForceRound = 0;

    this.extra_type = '';

    if (props.id) {
        this.id = props.id;
    }
    if (props.name) {
        this.name = props.name;
    }
    if (props.heroid) {
        this.heroid = props.heroid;
    }
    if (props.herodata) {
        this.herodata = props.herodata;
    }
    if (props.pos) {
        this.pos = props.pos;
    }
    if (props.team) {
        this.team = props.team;
    }
    if (props.heroimage) {
        this.heroimage = props.heroimage;
    }
    if (props.zindex) {
        this.zindex = props.zindex;
    }
    if (this.herodata.power > 0) { //power
        this.power = this.herodata.power;
    }
    this.init();
};

Hero.prototype.clear = function () {
    for (var i = 0, len = this.timeout.length; i < len; i++) {
        clearTimeout(this.timeout);
    }
    this.timeout = null;

    if (this.timer) {
        clearTimeout(this.timer);
    }
    this.timer = null;

    if (this.faintDom) {
        this.faintDom.stop();
        this.faintDom = null; //眩晕dom
    }
    if (this.e) {
        this.e.remove();
        this.e.empty();
        this.e = null;
    }
    if (this.body_wrap) {
        this.body_wrap.remove();
        this.body_wrap = null;
    }
    if (stage) {
        stage.remove();
        stage = null;
    }
    this.ini = null;
    this.heroid = 0;
    this.name = '';
    this.pos = 0;
    this.team = 'left';
    this.herodata = null; //数据 当前血量等
    this.heroimage = null; //图片url
    this.body = null; //人物身体动画
    this.zindex = 0;

    this.allblood = 0;
    this.blood = 0;
    this.power = 0; //士气(100max)
    this.poweranim = null;
    this.attackanim = null;

    this.isApparentDeath = false; // 是否是假死，假死时，即使血量为0也不死

    if (this.passiveSkillAnim) {
        this.passiveSkillAnim.destory();
        this.passiveSkillAnim = null;
    }

    this.shieldRound = 0;
    this.penetrate = null;
    this.penetrateRound = 0;
    this.attackForce = null;
    this.attackForceRound = 0;

    this.extra_type = '';

},

Hero.prototype.init = function() { //初始化 血条、名字

    /* 宽、高 */
    this.width = 315;
    this.height = 253;

    //先处理是否关卡BOSS等
    if(this.heroid == 99) {
        this.extra_type = 'guanqiaboss';
        this.width = 640;
        this.height = 320;
    //四象圣域的怪
    } else if(miscpop.in_array(this.heroid, [201, 204, 207, 210, 213])) {
        this.extra_type = 'sanctum_small';
        this.width = 360;
        this.height = 180;
    } else if(miscpop.in_array(this.heroid, [202, 205, 208, 211, 214])) {
        this.extra_type = 'sanctum_middle';
        this.width = 524;
        this.height = 262;
    } else if(miscpop.in_array(this.heroid, [203, 206, 209, 212, 215])) {
        this.extra_type = 'sanctum_big';
        this.width = 640;
        this.height = 320;
    }

    /* 配置 */
    if (this.team == 'left') { //左边
        this.ini.container_x = battle_pos[this.team][this.pos].x + hero_ini[this.heroid].mx; //外框x位置 点的位置：battle_pos[this.team][this.pos].x
        this.ini.body_wrap_x = 0;
        this.ini.body_wrap_scalX = 1;
        this.ini.shadow_x = hero_ini[this.heroid].sx;
        this.ini.bar_wrap_x = hero_ini[this.heroid].bx;
        this.ini.label_x = hero_ini[this.heroid].lx;
        this.ini.power_x = hero_ini[this.heroid].px;
    } else {
        this.ini.container_x = battle_pos[this.team][this.pos].x + hero_ini[this.heroid].mx + hero_ini[this.heroid].rx;
        this.ini.body_wrap_x = this.width;//315
        this.ini.body_wrap_scalX = -1;
        this.ini.shadow_x = this.width - 99 - hero_ini[this.heroid].sx;
        this.ini.bar_wrap_x = this.width - 70 - hero_ini[this.heroid].bx;
        this.ini.label_x = this.width - 92 - hero_ini[this.heroid].lx;
        this.ini.power_x = this.width - 150 - hero_ini[this.heroid].px;
    }

    /* 坐标 */
    this.x = this.ini.container_x;
    this.y = battle_pos[this.team][this.pos].y - this.height;

    this.zindex = helper.getBattleZindex(this.pos);
    this.e = $('<div id="' + this.id + '" class="battle_sprite ' + this.team + 'team ' + this.extra_type + '" style="left:' + this.x + 'px;top:' + this.y + 'px;z-index:' + this.zindex + '"></div>');

    this.body_wrap = $('<div class="battle_sprite_body_wrap ' + this.extra_type + '" style="top:' + hero_ini[this.heroid].my + 'px;' + (this.ini.body_wrap_scalX == -1 ? '-webkit-transform:scaleX(-1);': '') + '"></div>'); //角色全身容器

    var mc = $('<div id="stand_' + this.id + '" style="background:url(' + this.heroimage + ') 0 0 no-repeat;position:absolute;left:0px;top:' + (this.height - hero_ini[this.heroid].height) + 'px;width:' + hero_ini[this.heroid].width + 'px;height:' + hero_ini[this.heroid].height + 'px;"></div>');
    this.body_wrap.append(mc);
    mc = null;
    /*
    var mc = new movieClipClass({"img":this.heroimage, "width":hero_ini[this.heroid].width, "height":hero_ini[this.heroid].height, "x":0, "y":253 - hero_ini[this.heroid].height, "loop":true,
                "frames":hero_ini[this.heroid].animations});
    mc.appendTo(this.body_wrap);
    mc.play();
    mc = null;
    */

    /* 2.影子 */
    this.shadow = $('<div class="battle_sprite_shadow_wrap" style="left:' + this.ini.shadow_x + 'px;"></div>');

    //血条士气条
    this.allblood = this.herodata.allblood; //血量(数字)
    this.blood = this.herodata.blood > 0 ? this.herodata.blood: this.allblood;
    this.bar_wrap = $('<div class="battle_sprite_bar_wrap" style="left:' + this.ini.bar_wrap_x + 'px;"><div class="bar_blood" id="bar_blood_' + this.id + '" style="width:' + (66 * helper.min(this.blood / this.allblood, 1)) + 'px;"></div><div class="bar_power" id="bar_power_' + this.id + '" style="width:' + (66 * helper.min(this.power / 100, 1)) + 'px;"></div></div>');
    this.name_wrap = $('<div class="battle_sprite_name_wrap" style="left:' + (this.ini.bar_wrap_x - 40) + 'px;color:' + helper.getHeroColor(this.herodata.quality) + ';">' + this.name + '</div>');

    this.e.append(this.shadow);
    this.e.append(this.body_wrap);
    this.e.append(this.bar_wrap);
    this.e.append(this.name_wrap);

};
Hero.prototype.showSkill = function(isReducePower) {
    this.removePower();

    if (isReducePower) {
        this.setBar({
            power: this.power - 100
        });
    }

    if (this.team == 'left') {
        var left = battle_pos[this.team][this.pos]['x'] + 26;
        var top = battle_pos[this.team][this.pos]['y'] - 130;
    } else {
        var left = battle_pos[this.team][this.pos]['x'] - 122;
        var top = battle_pos[this.team][this.pos]['y'] - 130;
    }

    if (this.herodata.star == 1) {
        top -= 60;
        this.team == 'left' ? left -= 100 : left -= 370;
        var skill_wrap = $('<p class="skill_wrap special'+ (this.team == 'right' ? ' right' : '') +'" style="z-index:100;left:' + left + 'px;top:' + top + 'px"><span class="avatar" style="background-image: url(' + xGame.avatar(this.herodata.image) + ');"></span><em class="skill">' + this.herodata.skill + '</em></p>');
    } else {
        var skill_wrap = $('<p class="skill_wrap' + (this.herodata.skill.length > 5 ? ' two_line' : '') + '" style="z-index:100;left:' + left + 'px;top:' + top + 'px"><em>' + this.herodata.skill + '</em></p>');
    }
    stage.append(skill_wrap);

    this.timeout.push(setTimeout(function() {
        skill_wrap.remove();
    },
    900));

};
Hero.prototype.powerChange = function(changetype) {

    var barLeft = parseInt(this.bar_wrap.css('left'));
    var barWidth = this.bar_wrap.width();

    if (this.team == 'left') {
        var left = barLeft + barWidth;
    } else {
        var left = barLeft - 90;
    }

    var bottom = 75;
    var powerchange_wrap = $('<p class="powerchange_' + changetype + '" style="z-index:10;left:'+ left +'px;bottom:' + bottom + 'px"></p>');
    this.e.append(powerchange_wrap);

    this.timeout.push(setTimeout(function() {
        var _anim = changetype == 'up' ? 'fadeOutUp': 'fadeOutDown';
        xGame.doAnimate(powerchange_wrap, _anim, '0.4s',
        function() {
            powerchange_wrap.remove();
            powerchange_wrap = null;
        });
    },
    400));
};
Hero.prototype.showFaint = function() { //眩晕
    if (this.faintDom != null) {
        return false;
    }
    var left = battle_pos[this.team][this.pos]['x'] - 50;
    var top = battle_pos[this.team][this.pos]['y'] - 200;
    this.faintDom = $('<p class="faint_wrap" style="left:' + left + 'px;top:' + top + 'px"></p>');
    stage.append(this.faintDom);
};
Hero.prototype.hideFaint = function() {
    if (this.faintDom != null) {
        var _hero = this;
        _hero.faintDom.fadeOut(500,
        function() {
            $(this).remove();
            _hero.faintDom = null;
            _hero = null;
        });
    }
};
Hero.prototype.showNumber = function(num, isadd) {
    var value = "-" + num.toString();
    var numAnim = new Num({
        id: helper.createUUID("bloodnum"),
        src: staticUrl + 'img/battle/numtext' + (isadd == true ? '_add': '') + '.png',
        max: value.length,
        gap: 24,
        height: 30,
        x: 35 - (24 * value.length) / 2,
        y: -50,
        value: value,
        //数字字符串
        zindex: 20,
    });

    this.bar_wrap.append(numAnim.e);
    this.timeout.push(setTimeout(function() {
        numAnim.e.animate({
            opacity: 0,
            top: (numAnim.y - 20) + 'px'
        },
        500, 'ease-out',
        function() {
            numAnim.destory();
        });
    },
    400));
};
Hero.prototype.showLabel = function(type) {
    var that = this;
    var imagename = '';
    if (type == -1) {
        imagename = 'label_miss';
    } else if (type == -2) {
        imagename = 'label_viewbreak';
    } else if (type == -4) { //连击
        imagename = 'label_mulhit';
    } else if (type == 1) {
        imagename = 'label_hit';
    } else if (type == 'fanshang') {
        imagename = 'label_fanshang';
    }

    var label = $('<div class="battle_sprite_label ' + imagename + '" style="left:' + this.ini.label_x + 'px;top:' + hero_ini[this.heroid].ly + 'px;"></div>');
    this.e.append(label);
    
    this.timeout.push(setTimeout(function() {
            label.fadeOut(500, function () {
            $(this).remove();
        });
    },
    1000));
  
    if (type == -1) { //人物miss动画
        if (this.team == 'left') {
            var old_x = this.x;
            var new_x = this.x - 20;
        } else {
            var old_x = this.x;
            var new_x = this.x + 20;
        }
        this.e.animate({
            opacity: 0.8,
            left: new_x + 'px'
        },
        160, 'ease-out',
        function() {
            var self = $(this);
            that.timeout.push(setTimeout(function() {
                self.animate({
                    opacity: 1,
                    left: old_x + 'px'
                },
                160, 'ease-out');
            },
            100));
        })
    } else if (type == 1) { //暴击抖屏，暂时关闭
        //xGame.doAnimate($('#world'), 'shake', '0.3s');
    }

};
Hero.prototype.showSuffer = function(number) {
    /* 第一步：人物显示被攻击 */
    var self = this;
    var mc = $('<div class="suffer" style="background:url(' + xGame.hero(this.heroid, 3) + ') 0 0 no-repeat;position:absolute;left:0px;top:' + (this.height - hero_ini[this.heroid].height) + 'px;width:' + hero_ini[this.heroid].width + 'px;height:' + hero_ini[this.heroid].height + 'px;"></div>');
    self.body_wrap.append(mc);
    //setTimeout(function() {
    self.body_wrap.find('#stand_' + self.id).hide();
    //    },20);
    
    self.timer = setTimeout(function() {
        self.body_wrap.find('#stand_' + self.id).show();
        self.timer = null;
        //setTimeout(function() {
        mc.remove();
        mc = null;
        //},10);
    }, 600);

    self.timeout.push(self.timer);

    /* 第二步：减血 */
    this.showNumber(number); //TODO
    this.blood = this.blood - number;
    if (this.blood < 0) {
        this.blood = 0;
    }
    this.setBar({
        blood: this.blood
    });

    /* 第三步：播被攻击动画 */
    var x = battle_pos[this.team][this.pos]['x'] - 72;
    var y = battle_pos[this.team][this.pos]['y'] - 136;
    var scaleX = 1;
    if (this.team == 'right') {
        x += 44;
        scaleX = -1;
    }
    var attackanim = new movieClipClass({
        "img": staticUrl + 'img/battle/attackanim.png',
        "width": 110,
        "height": 145,
        "x": x,
        "y": y,
        "scaleX": scaleX,
        "loop": false,
        "endDestory": true,
        "zindex": 100,
        "frames": [{
            rect: [0, 0],
            label: "run",
            interval: 60
        },
        {
            rect: [110, 0],
            interval: 60
        },
        {
            rect: [220, 0],
            interval: 60
        },
        {
            rect: [330, 0],
            interval: 60
        },
        {
            rect: [440, 0],
            stop: 1,
            interval: 60
        },
        ]
    });
    stage.append(attackanim.obj); //attackanim.appendTo(stage);
    attackanim.play();
    attackanim = null;
};
Hero.prototype.showPower = function() {
    return false;
    this.poweranim = new movieClipClass({
        "img": staticUrl + 'img/battle/poweranim.png',
        "width": 150,
        "height": 151,
        "x": this.ini.power_x,
        "y": 110,
        "loop": true,
        "zindex": 20,
        "frames": [{
            rect: [0, 0],
            label: "run",
            interval: 50
        },
        {
            rect: [150, 0],
            interval: 50
        },
        {
            rect: [300, 0],
            interval: 50
        },
        {
            rect: [450, 0],
            interval: 50
        },
        {
            rect: [0, 150],
            interval: 50
        },
        {
            rect: [150, 150],
            interval: 50
        },
        {
            rect: [300, 150],
            interval: 50
        },
        {
            rect: [450, 150],
            interval: 50
        },
        ]
    });
    this.e.append(this.poweranim.obj);
    this.poweranim.play();

};
Hero.prototype.removePower = function() {
    return false;
    if (this.poweranim) {
        this.poweranim.destory();
        this.poweranim = null;
    }
};
Hero.prototype.toDie = function() {
    this.removePower();
    this.hideFaint();
    this.e.fadeOut(800,
    function() {
        $(this).remove();
    });
};
Hero.prototype.showShield = function() {
    if(this.shieldRound > 0) {
        this.shieldRound = 2;
        //console.log('盾牌补满');
        return false;
    }
    this.shieldRound = 2;
    var x = this.ini.label_x - 20;
    var y = 34;
    if(this.team == 'right') {
        x += 60;
    }

    var mc = $('<div class="shield '+ this.team +'" style="left:'+x+'px;bottom:'+y+'px;"></div>');

    this.e.append(mc);
};
Hero.prototype.reduceShieldRound = function() {
    if(this.shieldRound > 0) {
        //console.log('盾牌-1');
        this.shieldRound -= 1;
        if(this.shieldRound <= 0) {
            //console.log('隐藏盾牌');
            this.e.find('.shield').remove();
        }
    }
};
// 减小识破动画
Hero.prototype.showPenetrate = function () {

    if (this.team == 'left') {
        var left = battle_pos[this.team][this.pos]['x'] + 20;
        var top = battle_pos[this.team][this.pos]['y'] - 120;
    } else {
        var left = battle_pos[this.team][this.pos]['x'] - 100;
        var top = battle_pos[this.team][this.pos]['y'] - 120;
    }

    var dom = $('<div class="battle_penetrate_down" style="top:'+ top +'px;left:'+ left +'px;z-index:20"></div>');
    stage.append(dom);

    this.timeout.push(setTimeout(function() {
        xGame.doAnimate(dom, 'fadeOutDown', '0.4s',
        function() {
            dom.remove();
            dom = null;
        });
    },
    400));

    if (this.penetrateRound > 0) {
        this.penetrateRound = 2;
        return false;
    }

    this.penetrateRound = 2;

    if (this.penetrate != null) {
        return false;
    }

    var x = this.ini.shadow_x - 20;
    var y = 212;
    var time = 100;
    this.penetrate = new movieClipClass({
        'img': staticUrl + 'img/battle/battle_penetrate_down_anim.png',
        'width': 150,
        'height': 80,
        'x': x,
        'y': y,
        'loop': true,
        'endDestory': false,
        'frames': [
            {
                rect:[0, 0],
                interval: time
            },
            {
                rect:[150, 0],
                interval: time
            },
            {
                rect:[300, 0],
                interval: time
            },
            {
                rect:[450, 0],
                interval: time
            },
            {
                rect:[0, 80],
                interval: time
            },
            {
                rect:[150, 80],
                interval: time
            },
            {
                rect:[300, 80],
                interval: time
            },
            {
                rect:[450, 80],
                interval: time
            },
        ]
    });

    this.e.prepend(this.penetrate.obj);
    this.penetrate.play();
};
Hero.prototype.clearPenetrate = function () {

    if (this.penetrateRound > 0) {
        this.penetrateRound -= 1;

        if (this.penetrateRound <= 0 && this.penetrate != null) {
            this.penetrate.destory();
            this.penetrate = null;
        }
    }

};
// 提升攻击力动画
Hero.prototype.showAtk = function () {

    if (this.team == 'left') {
        var left = battle_pos[this.team][this.pos]['x'] + 20;
        var top = battle_pos[this.team][this.pos]['y'] - 120;
    } else {
        var left = battle_pos[this.team][this.pos]['x'] - 100;
        var top = battle_pos[this.team][this.pos]['y'] - 120;
    }

    var dom = $('<div class="battle_attack_up" style="top:'+ top +'px;left:'+ left +'px;z-index:20"></div>');

    this.timeout.push(setTimeout(function () {
        stage.append(dom);
    }, 500));

    this.timeout.push(setTimeout(function() {
        xGame.doAnimate(dom, 'fadeOutUp', '0.4s',
        function() {
            dom.remove();
            dom = null;
        });
    },
    700));

    if (this.attackForceRound > 0) {
        this.attackForceRound = 2;
        return false;
    }

    this.attackForceRound = 2;

    if (this.attackForce != null) {
        return false;
    }

    var x = this.ini.shadow_x - 50;
    var y = 137;
    var time = 100;
    this.attackForce = new movieClipClass({
        'img': staticUrl + 'img/battle/battle_attack_up_anim.png',
        'width': 200,
        'height': 150,
        'x': x,
        'y': y,
        'loop': true,
        'endDestory': false,
        'frames': [
            {
                rect:[0, 0],
                interval: time
            },
            {
                rect:[200, 0],
                interval: time
            },
            {
                rect:[400, 0],
                interval: time
            },
            {
                rect:[600, 0],
                interval: time
            },
            {
                rect:[800, 0],
                interval: time
            },
            {
                rect:[1000, 0],
                interval: time
            },
            {
                rect:[0, 150],
                interval: time
            },
            {
                rect:[200, 150],
                interval: time
            },
            {
                rect:[400, 150],
                interval: time
            },
            {
                rect:[600, 150],
                interval: time
            },
            {
                rect:[800, 150],
                interval: time
            },
            {
                rect:[1000, 150],
                interval: time
            },
        ]
    });

    this.e.prepend(this.attackForce.obj);
    this.attackForce.play();
};
Hero.prototype.clearAtk = function () {

    if (this.attackForceRound > 0) {
        this.attackForceRound -= 1;

        if (this.attackForceRound <= 0 && this.attackForce != null) {
            this.attackForce.destory();
            this.attackForce = null;
        }
    }
};
Hero.prototype.attackProcess = function(callback) {

    var nowHero = this;

    var mc = new movieClipClass({
        "img": xGame.hero(nowHero.heroid, 2, true),
        "width": hero_ini[nowHero.heroid].width,
        "height": hero_ini[nowHero.heroid].height,
        "x": 0,
        "y": this.height - hero_ini[nowHero.heroid].height,
        "loop": false,
        "endDestory": true,
        "callback": callback,
        "frames": hero_ini[nowHero.heroid].animations2,
    });
    mc.appendTo(nowHero.body_wrap);
    mc.play();
    mc = null;

    setTimeout(function () {
        nowHero.body_wrap.find('#stand_' + nowHero.id).hide();
    }, 10);
    //nowHero.attackanim.appendTo(nowHero.body_wrap);
    //nowHero.attackanim.play();
};
/*
{'launch': 'right', 'attackor':3, 'beattackors':[1,2], 'skill':1, 'hit':0, 'beAttackType':0, 'hurt':[{pos:1,blood:380}, {pos:2,blood:390}]}
*/
Hero.prototype.toAttack = function(fightdata) {
    var launchHero = this;
    var heroItem = null; // 暂存将领数据

    if (fightdata.attacktype == -3) { //解晕
        launchHero.timeout.push(setTimeout(function() {
            launchHero.hideFaint();
            battleWorld.fight(); //继续攻击
        },
        200));
        return false;
    }

    /* 开始啦 */

    var ox, oy, xx, yy;

    //目标方阵
    var to_team = fightdata.launch == 'left' ? 'right': 'left';
    //var to_pos = sprites[to_team][fightdata.beattackors[0]].pos;//第一目标
    var to_pos = fightdata.beattackors[0];
    var reduce_blood = fightdata.hurt; //伤害列表

    if (to_team == 'right') {
        ox = this.x;
        oy = this.y;

        xx = ox - battle_pos['left'][this.pos]['x'] + battle_pos['right'][to_pos]['x'] - 110;
        yy = oy - battle_pos['left'][this.pos]['y'] + battle_pos['right'][to_pos]['y'];
        if (hero_ini[launchHero.heroid].ax != undefined) { //攻击位置处理，有的可能攻击范围太大，需要往后移动点
            xx += hero_ini[launchHero.heroid].ax;
        }
    } else {
        ox = this.x;
        oy = this.y;

        xx = ox - battle_pos['right'][this.pos]['x'] + battle_pos['left'][to_pos]['x'] + 110;
        yy = oy - battle_pos['right'][this.pos]['y'] + battle_pos['left'][to_pos]['y'];
        if (hero_ini[launchHero.heroid].ax != undefined) { //攻击位置处理，有的可能攻击范围太大，需要往后移动点
            xx -= hero_ini[launchHero.heroid].ax;
        }
    }

    launchHero.nowIndex = helper.getBattleZindex(launchHero.pos); //launchHero.e.css('z-index');

    // 处理神诸葛的被动技能
    launchHero.showPassiveSkill(fightdata);

    //其他正常流程
    if (hero_ini[this.heroid].herotype == 1 && fightdata.skill == 0) { //猛将 位移进攻

        var _index = parseInt(sprites[to_team][fightdata.beattackors[0]].zindex) + 1;
        launchHero.e.css('z-index', _index);
        launchHero.e.animate({
            opacity: 0.2
        },
        30, 'ease-out',
        function() {
            launchHero.e.css({
                'opacity': 1,
                'left': xx + 'px',
                'top': yy + 'px'
            });
            var t1 = setTimeout(function() {
                var xcallback = function() {
                    if (fightdata.hit == 1) { //判断是否暴击
                        launchHero.showLabel(1);
                    }
                    launchHero.body_wrap.find('#stand_' + launchHero.id).show();
                    launchHero.afterSkillPlay(fightdata);
                    //launchHero.attackanim.destory();
                    //launchHero.attackanim = null;

                    if (fightdata.attacktype == -1) { //判断是否闪避 只第一目标显示
                        sprites[to_team][fightdata.beattackors[0]].showLabel( - 1);
                    } else if (fightdata.attacktype == -2) { //识破 只第一目标显示
                        sprites[to_team][fightdata.beattackors[0]].showLabel( - 2);
                        for (var ii in reduce_blood) { //打自己人！！
                            if (reduce_blood[ii].blood != 0) {
                                sprites[launchHero.team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                            }
                        }
                    } else { //普通
                        if (fightdata.attacktype == -4) { //连击
                            launchHero.showLabel( - 4);
                        }

                        // 处理套装加成效果
                        launchHero.showSuitEffect(fightdata);

                        for (var ii in reduce_blood) {

                            if (reduce_blood[ii].self == 0) {
                                if (reduce_blood[ii].percent) {
                                    //reduce_blood[ii].percent = 0.01;//deubg
                                    sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood, reduce_blood[ii].percent);
                                } else {
                                    sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                                }
                            }
                        }
                    }

                    var t2 = setTimeout(function() {
                        launchHero.e.css({
                            'opacity': 1,
                            'left': ox + 'px',
                            'top': oy + 'px',
                            'z-index': launchHero.nowIndex
                        });
                        launchHero.setBar({
                            power: fightdata.attackpower
                        });
                        if (fightdata.attacktype == -2) { //识破
                            for (var ii in reduce_blood) {
                                sprites[launchHero.team][reduce_blood[ii].pos].setBar({
                                    power: reduce_blood[ii].power
                                });
                            }
                        } else { //普通
                            for (var ii in reduce_blood) {
                                sprites[to_team][reduce_blood[ii].pos].setBar({
                                    power: reduce_blood[ii].power
                                });
                            }
                        }
                        temp = null;
                        reduce_blood = null;
                    }, 200);

                    var t3 = setTimeout(function () {
                        launchHero.removeBattleMask(fightdata); // 移除战斗遮罩
                        launchHero = null;
                        battleWorld.fight(); //继续攻击
                    }, launchHero.getBattleInterval());

                    launchHero.timeout.push(t2);
                    launchHero.timeout.push(t3);
                };

                launchHero.attackProcess(xcallback);

            },
            100);
            launchHero.timeout.push(t1);
        });

    } else { //谋士 或 技能攻击
        var t1 = setTimeout(function() {

            var xcallback = function() {
                //launchHero.attackanim.destory();
                //launchHero.attackanim = null;
                launchHero.body_wrap.find('#stand_' + launchHero.id).show();
                launchHero.afterSkillPlay(fightdata);

                if (fightdata.attacktype == -1) { //判断是否闪避 只第一目标显示
                    sprites[to_team][fightdata.beattackors[0]].showLabel( - 1);
                } else if (fightdata.attacktype == -2) { //识破 只第一目标显示
                    sprites[to_team][fightdata.beattackors[0]].showLabel( - 2);
                    for (var ii in reduce_blood) { //打自己人！！
                        if (reduce_blood[ii].blood != 0) {
                            sprites[launchHero.team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                        }
                    }
                } else { //攻击

                    // 处理套装加成效果
                    launchHero.showSuitEffect(fightdata);

                    if (fightdata.skill == 1 || fightdata.skill == 0) { //正常攻击
                        if (fightdata.attacktype == -4) { //连击
                            launchHero.showLabel( - 4);
                        }
                        for (var ii in reduce_blood) {

                            if (reduce_blood[ii].self == 0) {
                                if (reduce_blood[ii].percent) { //for boss
                                    reduce_blood[ii].percent = 0.01; //debug
                                    sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood, reduce_blood[ii].percent);
                                } else {
                                    sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                                }
                            }
                        }
                    } else if (fightdata.skill == 2 || fightdata.skill == 17 || fightdata.skill == 23 || fightdata.skill == 27) { //使眩晕
                        for (var ii in reduce_blood) {
                            if(reduce_blood[ii].self == 0) {
                                sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                                if (reduce_blood[ii].faint == 1) { //开始眩晕
                                    sprites[to_team][reduce_blood[ii].pos].showFaint();
                                }

                                if(fightdata.skill == 17 || fightdata.skill == 23 || fightdata.skill == 27) {//吸怒气
                                    sprites[to_team][reduce_blood[ii].pos].powerChange('down');
                                }
                            } else if(reduce_blood[ii].self == 1 && fightdata.skill == 17 || fightdata.skill == 23 || fightdata.skill == 27) {
                                sprites[launchHero.team][launchHero.pos].powerChange('up');
                            }
                        }

                    /*
                     * 3: 吸血，单体，所有只有2个数据
                     * 14: 3的基础上，增加恢复100怒气
                     * 15: 吸血
                     * 16: 吸血+恢复怒气
                     */
                    } else if (fightdata.skill == 3 || fightdata.skill == 14 || fightdata.skill == 15 || fightdata.skill == 16) {
                        var bloodPos = [];//加血的有哪些人
                        var bloodDict = {};//血量数值字典
                        var uniquePos = -1;//唯一加血的那个人
                        //step 1 检查是否有多个人的加血；把血数值合并
                        //console.log(reduce_blood);
                        for(var ii in reduce_blood) {
                            if (reduce_blood[ii].self == 1) {
                                if(!miscpop.in_array(reduce_blood[ii].pos, bloodPos)) {
                                    bloodPos.push(reduce_blood[ii].pos);
                                }
                                if(typeof bloodDict[reduce_blood[ii].pos] == 'undefined') {
                                    bloodDict[reduce_blood[ii].pos] = [];
                                }
                                bloodDict[reduce_blood[ii].pos].push(reduce_blood[ii].blood);
                            }
                        }
                        //说明只有单人加血，那唯一的位置就是我需要的
                        if(bloodPos.length == 1) {
                            uniquePos = bloodPos[0];
                        }
                        // console.log(bloodPos);
                        // console.log(bloodDict);
                        // console.log(uniquePos);
                        for (var ii in reduce_blood) {
                            if (reduce_blood[ii].self == 0) { //对方少血
                                sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                            } else { //自己的人加血(非合并加血)
                                if(uniquePos == -1) {
                                    heroItem = sprites[launchHero.team][reduce_blood[ii].pos];
                                    heroItem.setBar({
                                        blood: parseInt(heroItem.blood) + parseInt(reduce_blood[ii].blood)
                                    });
                                    heroItem.showNumber(reduce_blood[ii].blood, true);
                                }
                            }
                        }
                        //单人合并加血
                        if(uniquePos != -1) {
                            var mergeBlood = 0;
                            for(var i = 0; i < bloodDict[uniquePos].length; i++) {
                                mergeBlood += parseInt(bloodDict[uniquePos][i]);
                            }
                            heroItem = sprites[launchHero.team][uniquePos];

                            if (mergeBlood >= 0) {
                                heroItem.setBar({
                                    blood: parseInt(heroItem.blood) + mergeBlood
                                });
                                heroItem.showNumber(mergeBlood, true);
                            } else {
                                heroItem.showNumber(-mergeBlood);
                                heroItem.blood += mergeBlood;

                                if (heroItem.blood < 0) {
                                    heroItem.blood = 0;
                                }

                                heroItem.setBar({
                                    blood: heroItem.blood
                                });
                            }
                        }

                        if(fightdata.skill == 14 || fightdata.skill == 16) {
                            sprites[launchHero.team][launchHero.pos].powerChange('up');
                        }
                    } else if (fightdata.skill == 4) { //加血
                        for (var ii in reduce_blood) {
                            var _team = reduce_blood[ii].self == 0 ? to_team: launchHero.team; //哪一方队伍
                            var _blood = parseInt(sprites[_team][reduce_blood[ii].pos].blood) + parseInt(reduce_blood[ii].blood);
                            sprites[_team][reduce_blood[ii].pos].setBar({
                                blood: _blood
                            });
                            sprites[_team][reduce_blood[ii].pos].showNumber(reduce_blood[ii].blood, true);
                        }
                    } else if (fightdata.skill == 5) { //连击
                        for (var ii in reduce_blood) {
                            sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                        }
                    } else if (fightdata.skill == 6) { //减怒气
                        for (var ii in reduce_blood) {
                            var _team = reduce_blood[ii].self == 0 ? to_team: launchHero.team; //哪一方队伍
                            var _power = fightdata.skill == 6 ? 'down': 'up';
                            sprites[_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                            sprites[_team][reduce_blood[ii].pos].powerChange('down');
                        }
                    } else if (fightdata.skill == 7) { //加怒气
                        for (var ii in reduce_blood) {
                            var _team = reduce_blood[ii].self == 0 ? to_team: launchHero.team; //哪一方队伍
                            //sprites[_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                            sprites[_team][reduce_blood[ii].pos].powerChange('up');
                        }
                    } else if (fightdata.skill == 8) { //伤害、对方减士气、我方加士气
                        for (var ii in reduce_blood) {
                            var _team = reduce_blood[ii].self == 0 ? to_team: launchHero.team; //哪一方队伍
                            if (reduce_blood[ii].self == 0) { //对方少血、少士气
                                sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                                sprites[to_team][reduce_blood[ii].pos].powerChange('down');
                            } else { //自己加士气
                                sprites[_team][reduce_blood[ii].pos].powerChange('up');
                            }
                        }
                    } else if (fightdata.skill == 9 || fightdata.skill == 10 || fightdata.skill == 11) { //攻击附带加气
                        //console.log(reduce_blood);
                        //console.log(fightdata);
                        for (var ii in reduce_blood) {
                            if (reduce_blood[ii].self == 0) { //对方少血
                                sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                            } else { //自己加士气
                                sprites[launchHero.team][reduce_blood[ii].pos].powerChange('up');
                            }
                        }
                    } else if (fightdata.skill == 12 || fightdata.skill == 19 || fightdata.skill == 20 || fightdata.skill == 21 || fightdata.skill == 22 || fightdata.skill == 26) { //多倍伤害+100怒气
                        for (var ii in reduce_blood) {
                            if (reduce_blood[ii].self == 0) { //对方少血
                                sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);

                                if (fightdata.skill == 19) { // 识破动画
                                    sprites[to_team][reduce_blood[ii].pos].showPenetrate();
                                }
                            }
                        }

                        if (fightdata.skill == 20) {

                            var objSprite = sprites[launchHero.team];

                            for (var ii in objSprite) {
                                if (stage.find('#' + launchHero.team + ii).length != 0) {
                                    sprites[launchHero.team][ii].showAtk();
                                }
                            }
                        }

                        sprites[launchHero.team][launchHero.pos].powerChange('up');
                    } else if(fightdata.skill == 18) {//盾牌
                        for (var ii in reduce_blood) {
                            if (reduce_blood[ii].self == 0) { //对方少血
                                sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                            }
                        }
                        sprites[launchHero.team][launchHero.pos].powerChange('up');
                        sprites[launchHero.team][launchHero.pos].showShield();//盾牌在当前回合和下个回合显示
                    } else if (fightdata.skill == 24) { // 多倍伤害，自己加血，自己加怒气

                        var bloodData = {};
                        var allblood = 0;

                        for (var ii in reduce_blood) {
                            if (reduce_blood[ii].self == 0) { //对方少血
                                sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                            } else {

                                if (bloodData[reduce_blood[ii].pos] == null) {
                                    bloodData[reduce_blood[ii].pos] = [];
                                }

                                bloodData[reduce_blood[ii].pos].push(reduce_blood[ii].blood);
                            }
                        }

                        for (var ii in bloodData) {
                            allblood = 0;
                            heroItem = bloodData[ii];

                            for (var i = 0, len = heroItem.length; i < len; i++) {
                                allblood += parseInt(heroItem[i]);
                            }

                            heroItem = sprites[launchHero.team][ii];

                            if (allblood >= 0) {
                                heroItem.setBar({
                                    blood: parseInt(heroItem.blood) + allblood
                                });
                                heroItem.showNumber(allblood, true);
                            } else {
                                heroItem.showNumber(-allblood);
                                heroItem.blood += allblood;

                                if (heroItem.blood < 0) {
                                    heroItem.blood = 0;
                                }

                                heroItem.setBar({
                                    blood: heroItem.blood
                                });
                            }
                        }

                        sprites[launchHero.team][launchHero.pos].powerChange('up');
                    } else if (fightdata.skill == 25) { // 自己加士气，概率连击

                        if (fightdata.attacktype == -4) { //连击
                            launchHero.showLabel( - 4);
                        }

                        for (var ii in reduce_blood) {
                            if (reduce_blood[ii].self == 0) { //对方少血
                                sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                            }
                        }

                        launchHero.powerChange('up');
                    } else {//13 or other
                        for (var ii in reduce_blood) {
                            if (reduce_blood[ii].self == 0) { //对方少血
                                sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                            }
                        }
                    }
                }

                var t2 = setTimeout(function() {
                    launchHero.setBar({
                        power: fightdata.attackpower
                    });
                    if (fightdata.attacktype == -2) { //识破
                        for (var ii in reduce_blood) {
                            if (sprites[launchHero.team][reduce_blood[ii].pos]) {
                                sprites[launchHero.team][reduce_blood[ii].pos].setBar({
                                    power: reduce_blood[ii].power
                                });
                            }
                        }
                    } else { //普通
                        for (var ii in reduce_blood) {
                            var _team = reduce_blood[ii].self == 0 ? to_team: launchHero.team; //哪一方队伍
                            if (sprites[_team][reduce_blood[ii].pos]) {
                                sprites[_team][reduce_blood[ii].pos].setBar({
                                    power: reduce_blood[ii].power
                                });
                            }
                        }
                    }
                    /* recycle */
                    reduce_blood = null;
                    heroItem = null;
                }, 200);

                var t3 = setTimeout(function () {
                    launchHero.removeBattleMask(fightdata); // 移除战斗遮罩
                    launchHero = null;
                    battleWorld.fight(); //继续攻击
                }, launchHero.getBattleInterval());

                launchHero.timeout.push(t2);
                launchHero.timeout.push(t3);
            };

            if (fightdata.skill >= 1) { //判断是否绝技
                launchHero.showSkill(launchHero.checkReducePower(fightdata));

                if (launchHero.herodata.star == 1) {  // 如果当前武将星级大于5
                    launchHero.timeout.push(setTimeout(function() {
                        launchHero.attackProcess(xcallback);
                    }, 1000));
                } else {
                    launchHero.attackProcess(xcallback);
                }

            } else {
                launchHero.attackProcess(xcallback);
            }
            
            if (fightdata.hit == 1) { //判断是否暴击
                launchHero.showLabel(1);
            }

        },
        200);
        launchHero.timeout.push(t1);

    }

};
/* 设置bar
param: {blood:2000,power:50}
*/
Hero.prototype.setBar = function(param) {
    if (param.blood >= 0) {
        this.blood = param.blood;
        if (this.blood <= 0) {
            this.bar_wrap.find('#bar_blood_' + this.id).width(0);

            if (this.isApparentDeath == false) {
                this.removePower();
                this.hideFaint();
                this.toDie();
            }
        } else {
            this.bar_wrap.find('#bar_blood_' + this.id).width(66 * helper.min(this.blood / this.allblood, 1));
        }
    }
    if (param.power >= 0) {
        this.power = param.power;

        var xwidth = this.power <= 0 ? 0 : (66 * helper.min(this.power / 100, 1));
        this.bar_wrap.find('#bar_power_' + this.id).width(xwidth);
        if (param.power >= 100 && this.poweranim == null) {
            this.showPower();
        } else if (param.power < 100 && this.poweranim != null) {
            this.removePower();
        }

    }
};

// 处理套装加成效果
Hero.prototype.showSuitEffect = function (fightdata) {

    var item = null;
    var hurtData = fightdata.hurt;
    var skill = parseInt(fightdata.skill);
    var toTeam = this.team == 'left' ? 'right' : 'left';
    var number = 0;
    var skillArr1 = [11, 12, 14, 16, 18, 19, 20, 21, 22, 24, 25, 26]; // 自身加士气的技能
    var skillArr2 = [8, 17, 23, 27]; // 自身加士气，敌方减士气的技能
    var skillArr3 = [7, 9, 10]; // 己方加士气，但是自己不加的技能
    var skillArr4 = [6]; // 敌方减士气的技能
    var skillArr5 = [3, 14, 15, 16, 24]; // 具有吸血效果的技能

    for (var i = 0; i < hurtData.length; i++) {
        item = hurtData[i];

        if (item.self == 0) {
            continue;
        }

        // 吸血效果
        if (item.xixue == 1) {

            // 具有吸血效果的技能不进行处理
            if (!miscpop.in_array(skill, skillArr5)) {
                this.showNumber(item.blood, true);
                this.setBar({
                    blood: this.blood + parseInt(item.blood)
                });
                hurtData.splice(i, 1);
                i--;
            }
        } else if (item.touqi == 1) { // 偷气效果

            // 自身加士气
            if (miscpop.in_array(skill, skillArr1)) {
                // 敌人第一目标减士气
                sprites[toTeam][fightdata.beattackors[0]].powerChange('down');

            // 自身加士气，敌方减士气
            } else if (miscpop.in_array(skill, skillArr2)) {
                // 不做处理
            
            // 己方加士气，但是自己不加
            } else if (miscpop.in_array(skill, skillArr3)) {
                // 自己加士气，敌人第一目标减士气
                this.powerChange('up');
                sprites[toTeam][fightdata.beattackors[0]].powerChange('down');

            // 敌方减士气
            } else if (miscpop.in_array(skill, skillArr4)) {
                // 自身加士气
                this.powerChange('up');

            } else {
                // 自己加士气，敌人第一目标减士气
                this.powerChange('up');
                sprites[toTeam][fightdata.beattackors[0]].powerChange('down');
            }

            hurtData.splice(i, 1);
            i--;
        } else if (item.huiqi == 1) { // 回气效果

            // 自身加士气
            if (miscpop.in_array(skill, skillArr1)) {
                // 不做处理

            // 自身加士气，敌方减士气
            } else if (miscpop.in_array(skill, skillArr2)) {
                // 不做处理
            
            // 己方加士气，但是自己不加
            } else if (miscpop.in_array(skill, skillArr3)) {
                // 自己加士气
                this.powerChange('up');

            // 敌方减士气
            } else if (miscpop.in_array(skill, skillArr4)) {
                // 自身加士气
                this.powerChange('up');

            } else {
                // 自己加士气
                this.powerChange('up');
            }

            hurtData.splice(i, 1);
            i--;
        } else if (item.qingqi == 1) { // 清气效果

            // 自身加士气
            if (miscpop.in_array(skill, skillArr1)) {
                // 敌人第一目标减士气
                sprites[toTeam][fightdata.beattackors[0]].powerChange('down');

            // 自身加士气，敌方减士气
            } else if (miscpop.in_array(skill, skillArr2)) {
                // 不做处理
            
            // 己方加士气，但是自己不加
            } else if (miscpop.in_array(skill, skillArr3)) {
                // 敌人第一目标减士气
                sprites[toTeam][fightdata.beattackors[0]].powerChange('down');

            // 敌方减士气
            } else if (miscpop.in_array(skill, skillArr4)) {
                // 不做处理

            } else {
                // 敌人第一目标减士气
                sprites[toTeam][fightdata.beattackors[0]].powerChange('down');
            }

            hurtData.splice(i, 1);
            i--;
        } else if (item.fanshang == 1) { // 反伤效果

            // 具有吸血效果的技能不进行处理
            if (!miscpop.in_array(skill, skillArr5)) {
                number = -parseInt(item.blood);
                this.showNumber(number);
                this.blood = this.blood - number;

                if (this.blood < 0) {
                    this.blood = 0;
                }
                
                this.setBar({
                    blood: this.blood
                });
                
                hurtData.splice(i, 1);
                i--;
            }

            for (var j = 0, len = item.defpos.length; j < len; j++) {
                sprites[toTeam][item.defpos[j]].showLabel('fanshang');
            }
        }
    }
};

// 处理神诸葛的被动技能：空城
Hero.prototype.showPassiveSkill = function (fightdata) {

    var item = null;
    var hurtData = fightdata.hurt;
    var toTeam = null;
    var toPos = 0; // 被打的人的位置（即神诸葛的位置）

    // 找出触发空城技能的被打的人的位置
    for (var i = 0; i < hurtData.length; i++) {
        item = hurtData[i];

        // 为true时，神诸葛的被动技能被触发
        if (item.kongcheng > 0) {
            toPos = item.pos;

            if (item.self == 1) {
                toTeam = fightdata.launch;
                hurtData.splice(i, 1);
                i--;
            } else {
                toTeam = fightdata.launch == 'left' ? 'right' : 'left';
            }

            xGame.xsSet(player.info.uid + toTeam + 'kongcheng', item.kongcheng, true); // 存储加的血量
            xGame.xsSet(player.info.uid + toTeam + 'heroData', JSON.stringify({'team': toTeam, 'pos': toPos}), true); // 记录触发技能的将的位置数据
            sprites[toTeam][toPos].isApparentDeath = true;
        }

        // 为true时，停止敌人中的神诸葛的动画
        if (item.endkongcheng == 1) {

            if (item.self == 1) {
                toTeam = fightdata.launch;
            } else {
                toTeam = fightdata.launch == 'left' ? 'right' : 'left';
            }
            
            var heroData = xGame.xsGet(player.info.uid + toTeam + 'heroData', true);

            if (heroData != null) {
                heroData = JSON.parse(heroData);
                var hero = sprites[heroData.team][heroData.pos];

                if (hero.passiveSkillAnim != null) {
                    hero.passiveSkillAnim.destory();
                    hero.passiveSkillAnim = null;
                }
                
                xGame.xsRemove(player.info.uid + toTeam + 'heroData', true);
            }
        }
    }

    item = null;
};

// 攻击方播放攻击动画后的处理
Hero.prototype.afterSkillPlay = function (fightdata) {

    var team = ['left', 'right'];
    var item = null;
    var kongcheng = {};
    var leftHero = null;
    var rightHero = null;

    for (var i = 0, len = team.length; i < len; i++) {
        item = team[i];

        // 当神诸葛的被动技能触发时，添加战斗遮罩
        kongcheng[item] = xGame.xsGet(player.info.uid + item + 'kongcheng', true);

        if (kongcheng[item] != null) {
            this.appendBattleMask(fightdata);

            var heroData = xGame.xsGet(player.info.uid + item + 'heroData', true);

            if (heroData != null) {
                heroData = JSON.parse(heroData);

                if (heroData.team == 'left') {
                    leftHero = sprites[heroData.team][heroData.pos];
                } else {
                    rightHero = sprites[heroData.team][heroData.pos];
                }
            }
        }
    }

    if (leftHero != null && rightHero != null) {
        rightHero.showPassiveSkillName(function () {
            rightHero.showPassiveSkillAnim(kongcheng['right']);
            leftHero.showPassiveSkillAnim(kongcheng['left']);
        });
    } else {

        if (leftHero != null) {
            leftHero.showPassiveSkillName(function () {
                leftHero.showPassiveSkillAnim(kongcheng['left']);
            });
        }

        if (rightHero != null) {
            rightHero.showPassiveSkillName(function () {
                rightHero.showPassiveSkillAnim(kongcheng['right']);
            });
        }
    }

};

// 显示神诸葛被动技能的名字
Hero.prototype.showPassiveSkillName = function (callback) {

    if (this.team == 'left') {
        var left = battle_pos[this.team][this.pos]['x'] + 26;
        var top = battle_pos[this.team][this.pos]['y'] - 130;
    } else {
        var left = battle_pos[this.team][this.pos]['x'] - 122;
        var top = battle_pos[this.team][this.pos]['y'] - 130;
    }

    top -= 60;
    this.team == 'left' ? left -= 100 : left -= 370;
    var skill_wrap = $('<p class="skill_wrap special'+ (this.team == 'right' ? ' right' : '') +'" style="z-index:100;left:' + left + 'px;top:' + top + 'px"><span class="avatar" style="background-image: url(' + xGame.avatar(this.herodata.image) + ');"></span><em class="skill">' + lang('battle_zhuge_skill') + '</em></p>');
    stage.append(skill_wrap);

    this.timeout.push(setTimeout(function() {
        skill_wrap.remove();

        if (callback) {
            callback();
        }
    },
    900));
};

// 播放神诸葛的被动技能动画
Hero.prototype.showPassiveSkillAnim = function (addBlood) {

    var that = this;

    var anim = new playFrameAnimClass({
        'css': {
            'background-image': 'url('+ staticUrl + 'img/battle/battle_passive_skill1.png)',
            'left': '50%',
            'bottom': '-50px',
            'width': '280px',
            'height': '270px',
            'margin-left': '-140px',
            'z-index': 20,
        },
        'rowNum': 3,
        'colNum': 3,
        'frameNum': 9,
        'callback': function () {
            that.showNumber(addBlood, true); // 显示加血数字
            that.blood = parseInt(addBlood);
            that.setBar({'blood': that.blood}); // 更新血量
            that.isApparentDeath = false; // 人物不是处于假死状态
            that.passiveSkillAnim.appendTo(that.shadow);
            that.passiveSkillAnim.play();
        },
    });

    anim.appendTo(this.shadow);
    anim.play();

    that.passiveSkillAnim = new playFrameAnimClass({
        'css': {
            'background-image': 'url('+ staticUrl + 'img/battle/battle_passive_skill2.png)',
            'left': '50%',
            'bottom': '0',
            'width': '230px',
            'height': '200px',
            'margin-left': '-115px',
            'z-index': 20,
        },
        'rowNum': 5,
        'colNum': 4,
        'frameNum': 20,
        'loop': true,
    });
};

// 添加战斗遮罩层
Hero.prototype.appendBattleMask = function (fightdata) {

    // 增加战斗遮罩，调整攻受方层级
    var world = $('#battleworld_page');

    if (world.find('.skill_mask').length == 1) {
        return;
    }

    this.e.css('z-index', parseInt(this.nowIndex) + 20);
    var toTeam = fightdata.launch == 'left' ? 'right': 'left';
    var suffer = null;
    var item = null;

    if (fightdata.attacktype == -2 || fightdata.attacktype == -1) { // 识破或闪避
        suffer = sprites[toTeam][fightdata.beattackors[0]];
        suffer.e.css('z-index', parseInt(suffer.e.css('z-index')) + 20);
    } else {
        for (var i = 0, len = fightdata.hurt.length; i < len; i++) {

            item = fightdata.hurt[i];

            if (item.self == 0) {
                suffer = sprites[toTeam][item.pos];
            } else {
                suffer = sprites[fightdata.launch][item.pos];
            }

            suffer.e.css('z-index', (parseInt(suffer.e.css('z-index')) || 0) + 20);
        }
    }

    world.append('<div class="skill_mask" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:19;background:rgba(0,0,0,0.7)"></div>');
    world = null;
    suffer = null;
    item = null;
};

// 移除战斗遮罩层
Hero.prototype.removeBattleMask = function (fightdata) {

    var world = $('#battleworld_page');

    if (world.find('.skill_mask').length == 0) {
        return;
    }

    var toTeam = fightdata.launch == 'left' ? 'right': 'left';
    var suffer = null;
    var item = null;

    if (fightdata.attacktype == -2 || fightdata.attacktype == -1) { // 识破或闪避
        suffer = sprites[toTeam][fightdata.beattackors[0]];
        suffer.e.css('z-index', parseInt(suffer.e.css('z-index')) - 20);
    } else {
        for (var i = 0, len = fightdata.hurt.length; i < len; i++) {

            item = fightdata.hurt[i];

            if (item.self == 0) {
                suffer = sprites[toTeam][item.pos];
            } else {
                suffer = sprites[fightdata.launch][item.pos];
            }

            suffer.e.css('z-index', parseInt(suffer.e.css('z-index')) - 20);
        }
    }

    this.e.css('z-index', this.nowIndex);
    world.find('.skill_mask').remove();
    world = null;
    suffer = null;
    item = null;
};

// 检查放技能时是否减气
Hero.prototype.checkReducePower = function (fightdata) {

    var item = null;

    for (var i = 0, len = fightdata.hurt.length; i < len; i++) {
        item = fightdata.hurt[i];

        if (item.noy == 1) {
            return false;
        }
    }

    return true;
};

// 获取战斗时间间隔
Hero.prototype.getBattleInterval = function () {    
    var timeInterval = 200;
    var heroTeam = ['left', 'right'];
    var item = null;
    var kongcheng = null;

    for (var i = 0, len = heroTeam.length; i < len; i++) {
        item = heroTeam[i];

        // 神诸葛的被动技能触发时，延后下一回合开始的时间
        kongcheng = xGame.xsGet(player.info.uid + item + 'kongcheng', true);
        xGame.xsRemove(player.info.uid + item + 'kongcheng', true);

        if (kongcheng != null) {
            timeInterval = 2000;
        }
    }

    return timeInterval;
};

/* BOSS */
var Boss = function(props) {
    this.e = null; //元素
    this.ini = {};
    this.heroid = 0; //id
    this.name = '';
    this.pos = 0; //位置
    this.team = 'left'; //左边or右边
    this.herodata = null; //数据 当前血量等
    this.heroimage = null; //图片url
    this.body = null; //人物身体动画
    this.timer = null;
    this.timeout = [];
    this.isApparentDeath = false; // 是否是假死，假死时，即使血量为0也不死
    this.passiveSkillAnim = null; // 神诸葛的被动技能动画

    this.allblood = 0;
    this.blood = 0;

    this.attackanim = null;
    this.faintDom = null; //眩晕dom

    if (props.id) {
        this.id = props.id;
    }
    if (props.name) {
        this.name = props.name;
    }
    if (props.heroid) {
        this.heroid = props.heroid;
    }
    if (props.herodata) {
        this.herodata = props.herodata;
    }
    if (props.pos) {
        this.pos = props.pos;
    }
    if (props.team) {
        this.team = props.team;
    }
    if (props.heroimage) {
        this.heroimage = props.heroimage;
    }
    this.init();
};

Boss.prototype.clear = function() {
    this.ini = {};
    this.heroid = 0; 
    this.name = '';
    this.pos = 0;
    this.blood = 0;
    this.allblood = 0;
    this.herodata = null; 
    this.heroimage = null; 
    this.body = null;
    
    for (var i = 0, len = this.timeout.length; i < len; i++) {
        clearTimeout(this.timeout[i]);
    }
    this.timeout = null;
    
    if (this.timer) {
        clearTimeout(this.timer);
    }
    
    this.timer = null;
    
    if (this.e) {
        this.e.remove();
        this.e = null;
    }
    if (this.attackanim) {
        this.attackanim.remove();
        this.attackanim = null;
    }
    if (this.faintDom) {
        this.faintDom.remove();
        this.faintDom = null; 
    }

    this.isApparentDeath = false; // 是否是假死，假死时，即使血量为0也不死

    if (this.passiveSkillAnim) {
        this.passiveSkillAnim.destory();
        this.passiveSkillAnim = null;
    }
};

Boss.prototype.init = function() { //初始化 血条、名字

    this.ini.container_x = battle_pos[this.team][this.pos].x + hero_ini[this.heroid].mx + hero_ini[this.heroid].rx;
    this.ini.body_wrap_x = 525;
    this.ini.body_wrap_scalX = -1;
    this.ini.shadow_x = 525 - 99 - hero_ini[this.heroid].sx;
    this.ini.bar_wrap_x = 525 - 350 - hero_ini[this.heroid].bx;
    this.ini.label_x = 525 - 92 - hero_ini[this.heroid].lx;

    this.width = 525; //315
    this.height = 262; //253
    this.x = this.ini.container_x;
    this.y = battle_pos[this.team][this.pos].y - 256;

    this.e = $('<div id="' + this.id + '" class="battle_sprite ' + this.team + 'team battle_sprite_boss" style="left:' + this.x + 'px;top:' + this.y + 'px"></div>');

    this.body_wrap = $('<div class="battle_sprite_body_wrap battle_sprite_boss" style="' + (this.ini.body_wrap_scalX == -1 ? '-webkit-transform:scaleX(-1);': '') + '"></div>'); //角色全身容器

    var mc = $('<div id="stand_' + this.id + '" style="background:url(' + this.heroimage + ') 0 0 no-repeat;position:absolute;left:0px;top:0px;width:' + hero_ini[this.heroid].width + 'px;height:' + hero_ini[this.heroid].height + 'px;"></div>');
    this.body_wrap.append(mc);
    mc = null;

    /* 2.影子 */
    this.shadow = $('<div class="battle_sprite_shadow_wrap" style="left:' + this.ini.shadow_x + 'px;"></div>');
    
    //区分国家boss还是世界boss
    if (this.heroid == 'countryboss') {
        this.blood = this.herodata.blood; //血量(数字)
        this.allblood = this.herodata.allblood;
        this.bar_wrap = $('<div class="battle_boss_sprite_bar_wrap" style="left:' + this.ini.bar_wrap_x + 'px;"><div class="bar_blood" id="bar_blood_' + this.id + '" style="width:' + (348 * helper.min(this.blood / this.allblood, 1)) + 'px;"></div><p class="boss_blood_num">' + this.blood + ' / ' + this.allblood +'</p></div>');
    } else {
        //血条士气条
        if (this.herodata.blood > 0) {
            this.blood = this.herodata.blood;
            this.allblood = 100.00;
        } else {
            this.blood = this.herodata.allblood;
            this.allblood = 100.00;
        }
        this.bar_wrap = $('<div class="battle_boss_sprite_bar_wrap" style="left:' + this.ini.bar_wrap_x + 'px;"><div class="bar_blood" id="bar_blood_' + this.id + '" style="width:' + (348 * helper.min(this.blood / this.allblood, 1)) + 'px;"></div><p class="boss_blood_num">' + this.blood + '%</p></div>');
    }
    this.name_wrap = $('<div class="battle_sprite_name_wrap battle_boss_sprite_name_wrap" style="left:' + (this.ini.bar_wrap_x) + 'px;">' + this.name + '</div>');

    this.e.append(this.shadow);
    this.e.append(this.body_wrap);
    this.e.append(this.bar_wrap);
    this.e.append(this.name_wrap);

};
Boss.prototype.showNumber = function(num, isadd) {
    var value = "-" + num.toString();
    var numAnim = new Num({
        id: helper.createUUID("bloodnum"),
        src: staticUrl + 'img/battle/numtext' + (isadd == true ? '_add': '') + '.png',
        max: value.length,
        gap: 24,
        height: 30,
        x: 175 - (24 * value.length) / 2,
        y: -50,
        value: value,
        //数字字符串
    });

    this.bar_wrap.append(numAnim.e);
    this.timeout.push(setTimeout(function() {
        numAnim.e.animate({
            opacity: 0,
            top: (numAnim.y - 20) + 'px'
        },
        500, 'ease-out',
        function() {
            numAnim.destory();
        });
    },
    400));
};
Boss.prototype.showFaint = function() { //眩晕
    if (this.faintDom != null) {
        return false;
    }
    var left = battle_pos[this.team][this.pos]['x'] - 50;
    var top = battle_pos[this.team][this.pos]['y'] - 240;
    this.faintDom = $('<p class="faint_wrap" style="left:' + left + 'px;top:' + top + 'px"></p>');
    stage.append(this.faintDom);
};
Boss.prototype.hideFaint = function() {
    if (this.faintDom != null) {
        var _hero = this;
        _hero.faintDom.fadeOut(500,
        function() {
            $(this).remove();
            _hero.faintDom = null;
            _hero = null;
        });
    }
};
Boss.prototype.powerChange = function(changetype) {
    if (this.team == 'left') {
        var left = battle_pos[this.team][this.pos]['x'] + 40;
        var top = battle_pos[this.team][this.pos]['y'] - 170;
    } else {
        var left = battle_pos[this.team][this.pos]['x'] - 120;
        var top = battle_pos[this.team][this.pos]['y'] - 170;
    }
    var powerchange_wrap = $('<p class="powerchange_' + changetype + '" style="z-index:100;left:' + left + 'px;top:' + top + 'px"></p>');
    stage.append(powerchange_wrap);

    this.timeout.push(setTimeout(function() {
        var _anim = changetype == 'up' ? 'fadeOutUp': 'fadeOutDown';
        xGame.doAnimate(powerchange_wrap, _anim, '0.4s',
        function() {
            powerchange_wrap.remove();
            powerchange_wrap = null;
        });
    },
    400));
};
Boss.prototype.showLabel = function(type) {
    var that = this;
    var imagename = '';
    if (type == -1) {
        imagename = 'label_miss';
    } else if (type == -2) {
        imagename = 'label_viewbreak';
    } else if (type == -4) { //连击
        imagename = 'label_mulhit';
    } else if (type == 1) {
        imagename = 'label_hit';
    }

    var left = battle_pos[this.team][this.pos]['x'] - 120;
    var top = battle_pos[this.team][this.pos]['y'] - 170;

    var label = $('<div class="battle_sprite_label ' + imagename + '" style="left:' + left + 'px;top:' + top + 'px;"></div>');
    stage.append(label);
    this.timeout.push(setTimeout(function() {
        label.fadeOut(500,
        function() {
            $(this).remove();
        });
    },
    1000));

    if (type == -1) { //人物miss动画
        if (this.team == 'left') {
            var old_x = this.x;
            var new_x = this.x - 20;
        } else {
            var old_x = this.x;
            var new_x = this.x + 20;
        }
        this.e.animate({
            opacity: 0.8,
            left: new_x + 'px'
        },
        160, 'ease-out',
        function() {
            var self = $(this);
            that.timeout.push(setTimeout(function() {
                self.animate({
                    opacity: 1,
                    left: old_x + 'px'
                },
                160, 'ease-out');
            },
            100));
        })
    } else if (type == 1) { //暴击抖屏，暂时关闭
        //xGame.doAnimate($('#world'), 'shake', '0.3s');
    }

};
Boss.prototype.showSuffer = function(number, blood) {
    /* 第一步：人物显示被攻击 */
    var self = this;
    var path = '';

    switch (this.heroid) {
        case 'countryboss':
            path = 'pop/' + this.heroid + '_defense';
            break;
        case 'newyearboss':
            path = 'worldboss/' + this.heroid + '_defense';
            break;
        case 'worldboss1':
            path = 'worldboss/' + this.heroid + '.1';
            break;
    }
    var mc = $('<div class="suffer" style="background:url(' + staticUrl + 'img/' + path + '.png) 0 0 no-repeat;position:absolute;left:0px;top:0;width:' + hero_ini[this.heroid].width + 'px;height:' + hero_ini[this.heroid].height + 'px;"></div>');
    self.body_wrap.append(mc);
    //setTimeout(function() {
    self.body_wrap.find('#stand_' + self.id).hide();
    //    },20);
    self.timer = setTimeout(function() {
        self.body_wrap.find('#stand_' + self.id).show();
        self.timer = null;
        //setTimeout(function() {
        mc.remove();
        mc = null;
        //},10);
    }, 600);

    self.timeout.push(self.timer);

    /* 第二步：减血 */
    this.showNumber(number); //TODO
    //只有是国家boss的时候才扣血
    if (this.heroid == 'countryboss') {
        this.blood = this.blood - number;
    } else {
        this.blood = this.blood - blood;
    }
    
    if (this.blood < 0) {
        this.blood = 0;
    }
    this.setBar({
        blood: this.blood
    });

    /* 第三步：播被攻击动画 */
    var x = battle_pos[this.team][this.pos]['x'] - 62;
    var y = battle_pos[this.team][this.pos]['y'] - 210;
    var scaleX = 1;
    if (this.team == 'right') {
        x += 44;
        scaleX = -1;
    }
    if(this.heroid == 'newyearboss') {//年兽特殊处理
        x -= 20;
        y += 60;
    }
    var attackanim = new movieClipClass({
        "img": staticUrl + 'img/battle/attackanim.png',
        "width": 110,
        "height": 145,
        "x": x,
        "y": y,
        "scaleX": scaleX,
        "loop": false,
        "endDestory": true,
        "frames": [{
            rect: [0, 0],
            label: "run",
            interval: 60
        },
        {
            rect: [110, 0],
            interval: 60
        },
        {
            rect: [220, 0],
            interval: 60
        },
        {
            rect: [330, 0],
            interval: 60
        },
        {
            rect: [440, 0],
            stop: 1,
            interval: 60
        },
        ]
    });
    stage.append(attackanim.obj); //attackanim.appendTo(stage);
    attackanim.play();
    attackanim = null;

};
Boss.prototype.toDie = function() {
    this.e.fadeOut(800,
    function() {
        $(this).remove();
    });
};
Boss.prototype.showShield = function() {// 空方法

};
Boss.prototype.reduceShieldRound = function() {// 空方法

};
Boss.prototype.showPenetrate = function() {// 空方法
};
Boss.prototype.clearPenetrate = function() {// 空方法
};
Boss.prototype.showAtk = function() {// 空方法
};
Boss.prototype.clearAtk = function() {// 空方法
};
Boss.prototype.attackProcess = function(callback) {

    var nowHero = this;
    var path = '';

    switch (this.heroid) {
        case 'countryboss':
            path = 'pop/' + this.heroid + '_attack';
            break;
        case 'newyearboss':
            path = 'worldboss/' + this.heroid + '_attack';
            break;
        case 'worldboss1':
            path = 'worldboss/' + this.heroid + '.a';
            break;
    }
    var mc = new movieClipClass({
        "img": staticUrl + 'img/' + path + '.png',
        "width": hero_ini[nowHero.heroid].width,
        "height": hero_ini[nowHero.heroid].height,
        "x": 0,
        "y": 0,
        "loop": false,
        "endDestory": true,
        "callback": callback,
        "frames": hero_ini[nowHero.heroid].animations2,
    });
    mc.appendTo(nowHero.body_wrap);
    mc.play();
    mc = null;

    setTimeout(function () {
        nowHero.body_wrap.find('#stand_' + nowHero.id).hide();
    }, 10);
};

Boss.prototype.toAttack = function(fightdata) {

    var launchHero = this;

    if (fightdata.attacktype == -3) { //解晕
        launchHero.timeout.push(setTimeout(function() {
            launchHero.hideFaint();
            battleWorld.fight(); //继续攻击
        },
        200));
        return false;
    }

    //目标方阵
    var to_team = fightdata.launch == 'left' ? 'right': 'left';
    var to_pos = sprites[to_team][fightdata.beattackors[0]].pos; //第一目标
    var reduce_blood = fightdata.hurt; //伤害列表

    // 处理神诸葛的被动技能
    launchHero.showPassiveSkill(fightdata);

    var t1 = setTimeout(function() {

        var xcallback = function() {
            launchHero.body_wrap.find('#stand_' + launchHero.id).show();
            launchHero.afterSkillPlay(fightdata);

            if (fightdata.attacktype == -1) { //判断是否闪避 只第一目标显示
                sprites[to_team][fightdata.beattackors[0]].showLabel( - 1);
            } else { //普通
                for (var ii in reduce_blood) {
                    sprites[to_team][reduce_blood[ii].pos].showSuffer(reduce_blood[ii].blood);
                }
            }

            var t2 = setTimeout(function() {

                for (var ii in reduce_blood) {
                    sprites[to_team][reduce_blood[ii].pos].setBar({
                        power: reduce_blood[ii].power
                    });
                }

                /* recycle */
                reduce_blood = null;
            },
            200);

            var t3 = setTimeout(function () {
                launchHero.removeBattleMask(fightdata); // 移除战斗遮罩
                launchHero = null;
                battleWorld.fight(); //继续攻击
            }, launchHero.getBattleInterval());

            launchHero.timeout.push(t2);
            launchHero.timeout.push(t3);
        };

        launchHero.attackProcess(xcallback);

    },
    200);
    launchHero.timeout.push(t1);

};
/* 设置bar
param: {blood:2000,power:50}
*/
Boss.prototype.setBar = function(param) {
    if (param.blood >= 0) {
        if (this.heroid == 'countryboss') {
            this.blood = param.blood;
        } else {
            this.blood = parseFloat(param.blood).toFixed(2);
        }
        if (this.blood <= 0) {
            this.bar_wrap.find('#bar_blood_' + this.id).width(0);
            if (this.heroid == 'countryboss') {
                this.bar_wrap.find('.boss_blood_num').html('0 / ' + this.allblood);
            } else {
                this.bar_wrap.find('.boss_blood_num').html('0%');
            }
            this.toDie();
        } else {
            this.bar_wrap.find('#bar_blood_' + this.id).width(348 * helper.min(this.blood / this.allblood, 1));
            
            if (this.heroid == 'countryboss') {
                this.bar_wrap.find('.boss_blood_num').html(this.blood + ' / ' + this.allblood);
            } else {
                this.bar_wrap.find('.boss_blood_num').html(this.blood + '%');
            }
        }
    }
};

// 处理神诸葛的被动技能：空城
Boss.prototype.showPassiveSkill = function (fightdata) {

    var item = null;
    var hurtData = fightdata.hurt;
    var toTeam = null;
    var toPos = 0; // 被打的人的位置（即神诸葛的位置）

    // 找出触发空城技能的被打的人的位置
    for (var i = 0; i < hurtData.length; i++) {
        item = hurtData[i];

        // 为true时，神诸葛的被动技能被触发
        if (item.kongcheng > 0) {
            toPos = item.pos;

            if (item.self == 1) {
                toTeam = fightdata.launch;
                hurtData.splice(i, 1);
                i--;
            } else {
                toTeam = fightdata.launch == 'left' ? 'right' : 'left';
            }

            xGame.xsSet(player.info.uid + toTeam + 'kongcheng', item.kongcheng, true); // 存储加的血量
            xGame.xsSet(player.info.uid + toTeam + 'heroData', JSON.stringify({'team': toTeam, 'pos': toPos}), true); // 记录触发技能的将的位置数据
            sprites[toTeam][toPos].isApparentDeath = true;
        }

        // 为true时，停止敌人中的神诸葛的动画
        if (item.endkongcheng == 1) {

            if (item.self == 1) {
                toTeam = fightdata.launch;
            } else {
                toTeam = fightdata.launch == 'left' ? 'right' : 'left';
            }
            
            var heroData = xGame.xsGet(player.info.uid + toTeam + 'heroData', true);

            if (heroData != null) {
                heroData = JSON.parse(heroData);
                var hero = sprites[heroData.team][heroData.pos];

                if (hero.passiveSkillAnim != null) {
                    hero.passiveSkillAnim.destory();
                    hero.passiveSkillAnim = null;
                }
                
                xGame.xsRemove(player.info.uid + toTeam + 'heroData', true);
            }
        }
    }

    item = null;
};

// 攻击方播放攻击动画后的处理
Boss.prototype.afterSkillPlay = function (fightdata) {

    var team = ['left', 'right'];
    var item = null;
    var kongcheng = {};
    var leftHero = null;
    var rightHero = null;

    for (var i = 0, len = team.length; i < len; i++) {
        item = team[i];

        // 当神诸葛的被动技能触发时，添加战斗遮罩
        kongcheng[item] = xGame.xsGet(player.info.uid + item + 'kongcheng', true);

        if (kongcheng[item] != null) {
            this.appendBattleMask(fightdata);

            var heroData = xGame.xsGet(player.info.uid + item + 'heroData', true);

            if (heroData != null) {
                heroData = JSON.parse(heroData);

                if (heroData.team == 'left') {
                    leftHero = sprites[heroData.team][heroData.pos];
                } else {
                    rightHero = sprites[heroData.team][heroData.pos];
                }
            }
        }
    }

    if (leftHero != null && rightHero != null) {
        rightHero.showPassiveSkillName(function () {
            rightHero.showPassiveSkillAnim(kongcheng['right']);
            leftHero.showPassiveSkillAnim(kongcheng['left']);
        });
    } else {

        if (leftHero != null) {
            leftHero.showPassiveSkillName(function () {
                leftHero.showPassiveSkillAnim(kongcheng['left']);
            });
        }

        if (rightHero != null) {
            rightHero.showPassiveSkillName(function () {
                rightHero.showPassiveSkillAnim(kongcheng['right']);
            });
        }
    }

};

// 显示神诸葛被动技能的名字
Boss.prototype.showPassiveSkillName = function (callback) {

    if (this.team == 'left') {
        var left = battle_pos[this.team][this.pos]['x'] + 26;
        var top = battle_pos[this.team][this.pos]['y'] - 130;
    } else {
        var left = battle_pos[this.team][this.pos]['x'] - 122;
        var top = battle_pos[this.team][this.pos]['y'] - 130;
    }

    top -= 60;
    this.team == 'left' ? left -= 100 : left -= 370;
    var skill_wrap = $('<p class="skill_wrap special'+ (this.team == 'right' ? ' right' : '') +'" style="z-index:100;left:' + left + 'px;top:' + top + 'px"><span class="avatar" style="background-image: url(' + xGame.avatar(this.herodata.image) + ');"></span><em class="skill">' + lang('battle_zhuge_skill') + '</em></p>');
    stage.append(skill_wrap);

    this.timeout.push(setTimeout(function() {
        skill_wrap.remove();

        if (callback) {
            callback();
        }
    },
    900));
};

// 播放神诸葛的被动技能动画
Boss.prototype.showPassiveSkillAnim = function (addBlood) {

    var that = this;

    var anim = new playFrameAnimClass({
        'css': {
            'background-image': 'url('+ staticUrl + 'img/battle/battle_passive_skill1.png)',
            'left': '50%',
            'bottom': '-50px',
            'width': '280px',
            'height': '270px',
            'margin-left': '-140px',
            'z-index': 20,
        },
        'rowNum': 3,
        'colNum': 3,
        'frameNum': 9,
        'callback': function () {
            that.showNumber(addBlood, true); // 显示加血数字
            that.blood = parseInt(addBlood);
            that.setBar({'blood': that.blood}); // 更新血量
            that.isApparentDeath = false; // 人物不是处于假死状态
            that.passiveSkillAnim.appendTo(that.shadow);
            that.passiveSkillAnim.play();
        },
    });

    anim.appendTo(this.shadow);
    anim.play();

    that.passiveSkillAnim = new playFrameAnimClass({
        'css': {
            'background-image': 'url('+ staticUrl + 'img/battle/battle_passive_skill2.png)',
            'left': '50%',
            'bottom': '0',
            'width': '230px',
            'height': '200px',
            'margin-left': '-115px',
            'z-index': 20,
        },
        'rowNum': 5,
        'colNum': 4,
        'frameNum': 20,
        'loop': true,
    });
};

// 添加战斗遮罩层
Boss.prototype.appendBattleMask = function (fightdata) {

    // 增加战斗遮罩，调整攻受方层级
    var world = $('#battleworld_page');

    if (world.find('.skill_mask').length == 1) {
        return;
    }

    this.e.css('z-index', parseInt(this.nowIndex) + 20);
    var toTeam = fightdata.launch == 'left' ? 'right': 'left';
    var suffer = null;
    var item = null;

    if (fightdata.attacktype == -2 || fightdata.attacktype == -1) { // 识破或闪避
        suffer = sprites[toTeam][fightdata.beattackors[0]];
        suffer.e.css('z-index', parseInt(suffer.e.css('z-index')) + 20);
    } else {
        for (var i = 0, len = fightdata.hurt.length; i < len; i++) {

            item = fightdata.hurt[i];

            if (item.self == 0) {
                suffer = sprites[toTeam][item.pos];
            } else {
                suffer = sprites[fightdata.launch][item.pos];
            }

            suffer.e.css('z-index', (parseInt(suffer.e.css('z-index')) || 0) + 20);
        }
    }

    world.append('<div class="skill_mask" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:19;background:rgba(0,0,0,0.7)"></div>');
    world = null;
    suffer = null;
    item = null;
};

// 移除战斗遮罩层
Boss.prototype.removeBattleMask = function (fightdata) {

    var world = $('#battleworld_page');

    if (world.find('.skill_mask').length == 0) {
        return;
    }

    var toTeam = fightdata.launch == 'left' ? 'right': 'left';
    var suffer = null;
    var item = null;

    if (fightdata.attacktype == -2 || fightdata.attacktype == -1) { // 识破或闪避
        suffer = sprites[toTeam][fightdata.beattackors[0]];
        suffer.e.css('z-index', parseInt(suffer.e.css('z-index')) - 20);
    } else {
        for (var i = 0, len = fightdata.hurt.length; i < len; i++) {

            item = fightdata.hurt[i];

            if (item.self == 0) {
                suffer = sprites[toTeam][item.pos];
            } else {
                suffer = sprites[fightdata.launch][item.pos];
            }

            suffer.e.css('z-index', parseInt(suffer.e.css('z-index')) - 20);
        }
    }

    this.e.css('z-index', this.nowIndex);
    world.find('.skill_mask').remove();
    world = null;
    suffer = null;
    item = null;
};

// 检查放技能时是否减气
Boss.prototype.checkReducePower = function (fightdata) {

    var item = null;

    for (var i = 0, len = fightdata.hurt.length; i < len; i++) {
        item = fightdata.hurt[i];

        if (item.noy == 1) {
            return false;
        }
    }

    return true;
};

// 获取战斗时间间隔
Boss.prototype.getBattleInterval = function () {    
    var timeInterval = 200;
    var heroTeam = ['left', 'right'];
    var item = null;
    var kongcheng = null;

    for (var i = 0, len = heroTeam.length; i < len; i++) {
        item = heroTeam[i];

        // 神诸葛的被动技能触发时，延后下一回合开始的时间
        kongcheng = xGame.xsGet(player.info.uid + item + 'kongcheng', true);
        xGame.xsRemove(player.info.uid + item + 'kongcheng', true);

        if (kongcheng != null) {
            timeInterval = 2000;
        }
    }

    return timeInterval;
};