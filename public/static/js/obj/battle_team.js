/* 团队
var team1 = new Team({id:'team1', line:1, direction:'left', name:'天地无极', level:18, blood: 100, allblood: 100, matrix:{}}});
*/
var Team = function(props) {
    this.e = null; //元素
    this.teamid = '';
    this.uid = 0;
    this.line = 0; //位置
    this.direction = ''; //left/right
    this.name = '';
    this.level = 0;
    this.blood = 0;
    this.allblood = 0;
    this.matrix = null; //队伍信息{p1:10, p2: 3...}

    if (props.teamid) {
        this.teamid = props.teamid;
    }
    if (props.uid) {
        this.uid = props.uid;
    }
    if (props.line) {
        this.line = props.line;
    }
    if (props.direction) {
        this.direction = props.direction;
    }
    if (props.name) {
        this.name = props.name;
    }
    if (props.level) {
        this.level = props.level;
    }
    if (props.blood) {
        this.blood = props.blood;
    }
    if (props.allblood) {
        this.allblood = props.allblood;
    }
    if (props.matrix) {
        this.matrix = props.matrix;
    }

    this.init();
};

Team.prototype.init = function() { //初始化 血条、名字

    //容器
    this.e = $('<div id="' + this.teamid + '" class="team_wrap ' + (this.direction == 'right' ? 'rightteam_wrap': '') + '"></div>');
    var html = '';
    //添加角色
    for (var i = 1;i <= 9;i ++) {
        if (typeof this.matrix[i] != 'undefined') {
            html += '<span class="p' + i + '" style="background:url(' + xGame.miniavatar(this.matrix[i]) + ') 0 0 no-repeat;"><em></em></span>';
        }
    }
    //名字、血条
    html += '<p class="name">' + this.name + '</p>';
    //血条
    var bloodpx = (this.blood / this.allblood).toFixed(3) * 190;
    html += '<div class="blood_wrap"><p style="width:' + bloodpx + 'px;"></p></div>';

    this.e.append(html);
};
Team.prototype.die = function(data) {
    if (data === true) { //干掉整个队伍dom
        this.e.remove();
        /* this.e.fadeOut(300,
        function() {
            $(this).remove();
        }); */
    } else { //挨个干掉队伍里的人
        for (var i = 0; i < data.length; i++) {
            this.e.find('.p' + data[i]).remove();
           /*  this.e.find('.p' + data[i]).fadeOut(300,
            function() {
                $(this).remove();
            }); */
            //delete this.matrix[data[i]];
        }
    }
};
Team.prototype.showSuffer = function(number) {
    this.showNumber(number, number < 0 ? true: false);
    this.blood = this.blood - number;
    if (this.blood < 0) {
        this.blood = 0;
    }
    this.setBar({
        blood: this.blood
    });
};
Team.prototype.showNumber = function(num, isadd) {
    var value = (isadd ? "" : "-") + num.toString();
    var numAnim = new Num({
        id: helper.createUUID("bloodnum"),
        src: staticUrl + 'img/battle/numtext' + (isadd == true ? '_add': '') + '.png',
        max: value.length,
        gap: 24,
        height: 30,
        x: 124 - (24 * value.length) / 2,
        y: 60,
        value: value,
        //数字字符串
        zindex: 20,
    });

    this.e.append(numAnim.e);
    setTimeout(function() {
        numAnim.e.animate({
            opacity: 0,
            top: (numAnim.y - 20) + 'px'
        },
        400, 'ease-out',
        function() {
            numAnim.destory();
        });
    },
    400);
};
Team.prototype.setBar = function(param) {
    if (param.blood >= 0) {
        this.blood = parseFloat(param.blood).toFixed(2);
        if (this.blood <= 0) {
            this.e.find('.blood_wrap').children('p').width(0);
            this.die(true);
        } else {
            this.e.find('.blood_wrap').children('p').width((this.blood / this.allblood).toFixed(3) * 190);
        }
    }
};
/* 仅仅用于将准备场景里队伍的血量更新 */
Team.prototype.updateBlood = function(param) {
    this.allblood = param.allblood;
    this.setBar({
        blood: param.blood
    });
};