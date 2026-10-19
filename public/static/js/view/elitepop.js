/* 精英副本 */
(function() {
    elitePopClass = function() {
        this.e;
        this.lefttimes = 0;
        this.fid = 0; //当前军团ID
        this.fieldname = ''; //当前军团名字
        this.teamid = 0; //当前队伍ID
        this.server = 'http://192.168.1.91:3000';
        this.socket; //用户的socket连接
        this.teamlist = []; //当前军团下的队伍列表
        this.teamdetail; //当前队伍详细信息
        this.fScroll; //军团列表
        this.teamScroller; //队伍列表
        this.background = false; //是否在后台

        this.target = null; //从外部直接进入某场景 {fid: 0, teamid: 0, countryid: 0}
        this.islock = false;
        this.teamlistTimer = null;
        this.teamlistData = [];
        this.isTimeout = [];
    };
    elitePopClass.prototype = {
        init: function(target) {

            //保存外部数据，连接上服务器后直接跳转过去
            this.target = target;

            this.get_server();
        },

        tempLock: function() {
            var that = this;

            that.islock = true;
            this.isTimeout.push(setTimeout(function() {
                that.islock = false;
            },
            300));
        },

        get_server: function() {
            var that = this;

            var render = function(res) {
                if (res.status == 1) {
                    that.server = res.url;
                    //连接服务器
                    that.socketInit();
                } else if (res.status == -1) {
                    xGame.toast.show(lang('team_closed'));
                    that.hide(true);
                } else {
                    xGame.toast.show(lang('can_not_join_elite'));
                    that.hide(true);
                }
            };
            loadContents({
                'c': 'elite',
                'm': 'get_server'
            },
            render);
            render = null;
        },

        /* 连接服务器 */
        socketInit: function() {
            var that = this;

            that.socket = io.connect(that.server, {
                'force new connection': true,
                'connect timeout': 5000
            });
            /* 连接失败 */
            that.socket.on('connect_failed',
            function() {
                xGame.toast.show(lang('can_not_join_elite'));
                that.hide(true);
            });
            that.socket.on('error',
            function() {
                xGame.toast.show(lang('can_not_join_elite'));
                that.hide(true);
            });
            /* 开始 */
            that.socket.on('connect',
            function(msg) {
                console.log('connection!');
                that.buildPage();
                that.bindTeamlistTimer();
                that.bindSocket();
                that.loginServer();
                if (typeof activitypop != 'undefined' && activitypop.e != null) {
                    activitypop.hide();
                }
                if (typeof chatpop != 'undefined' && chatpop.e != null) {
                    chatpop.hide();
                }
            });
        },

        /* 列表不再实时刷新，用一个定时器来管理（在0点左右，会特别卡，也容易出错） */
        bindTeamlistTimer: function() {
            var that = this;

            that.teamlistTimer = setInterval(function() {
                if(that.teamlistData.length > 0) {
                    //console.log('check and deal');
                    var data = that.teamlistData.pop();
                    that.buildTeamList(data);
                    that.teamlistData.length = 0;
                }
            }, 500);
        },

        clearTeamlistTimer: function() {
            clearInterval(this.teamlistTimer);
            this.teamlistTimer = null;
        },

        buildPage: function() {
            var that = this;

            that.e = xGame.loadPopTmpl('elite');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                elitepop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            //顶部军团列表
            that.e.find('#elitepop_top').undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                if (that.teamid > 0) { //已经在队伍里，不能够再去查看其他的军团信息
                    return false;
                }
                var fid = parseInt($(this).attr('fid'));
                var fieldname = $(this).attr('fieldname');
                if (fid != that.fid && fid > 0 && fieldname) {
                    that.fid = fid;
                    that.fieldname = fieldname;

                    that.e.find('#elitepop_top').find('li').removeClass('now');
                    $(this).addClass('now');

                    that.teamlist.length = 0; //先清掉数据才行
                    that.fetchTeamList(fid);
                }
            });
            //左侧队伍列表
            that.e.find('#elitepop_left').undelegate('.pop_btn_middle', 'click').delegate('.pop_btn_middle', 'click',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                if (that.teamdetail && that.teamdetail.num && that.teamdetail.num > 0) {
                    xGame.toast.show(lang('you_are_in_team'));
                    return false;
                }
                var _teamid = $(this).attr('teamid');
                var _countryid = $(this).attr('countryid');
                if (_teamid > 0) {
                    that.joinTeam(_teamid, _countryid);
                }
            });
            //选择创建队伍的类别
            that.e.find('#teamtype_wrap').undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if ($(this).attr('teamtype') == '2' && player.info.country == '0') {
                    xGame.toast.show(lang('no_country_to_create_team'));
                    return false;
                }
                that.e.find('#teamtype_wrap').children('li').removeClass('now');
                $(this).addClass('now');
            });
            //普通创建队伍
            that.e.find('#elitepop_right_out').undelegate('#createteam_btn', 'touchend').delegate('#createteam_btn', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                var _teamtype = that.e.find('#elitepop_right_out').find('.now').attr('teamtype');
                that.createTeam(_teamtype);
            });
            //至尊队伍
            that.e.find('#elitepop_right_out').undelegate('#topcreateteam_btn', 'touchend').delegate('#topcreateteam_btn', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                if (player.info.vip < 8) {
                    xGame.toast.show(lang('only_vip8_create_team'));
                    return false;
                }
                var _teamtype = that.e.find('#elitepop_right_out').find('.now').attr('teamtype');
                that.createTeam(_teamtype, true);
                
            });
            /*
            if(player.info.vip < 8) {//VIP才可显示至尊
                that.e.find('#topteam_addvalue').hide();
                that.e.find('#topcreateteam_btn').hide();
            }*/
            //退出队伍
            that.e.find('#elitepop_right_in').undelegate('#elitepop_exit_btn', 'touchend').delegate('#elitepop_exit_btn', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                that.exitTeam();
            });
            //解散队伍
            that.e.find('#elitepop_right_in').undelegate('#elitepop_fire_btn', 'touchend').delegate('#elitepop_fire_btn', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                that.exitTeam(true);
            });
            //聊天
            that.e.find('#elitepop_right_in').undelegate('#elitepop_chat_btn', 'touchend').delegate('#elitepop_chat_btn', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                xGame.showpop('chat');
            });
            that.e.find('#elitepop_right_out').undelegate('#elitepop_chat_btn2', 'touchend').delegate('#elitepop_chat_btn2', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                xGame.showpop('chat');
            });
            //聊天邀请
            that.e.find('#elitepop_right_in').undelegate('#elitepop_invite_btn', 'touchend').delegate('#elitepop_invite_btn', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                that.chatInvite();
            });
            //队伍位置调整
            that.e.find('#elitepop_right_in').undelegate('.order_btn', 'touchend').delegate('.order_btn', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                if (that.teamdetail && that.teamdetail.leader == player.info.uid) {
                    var _btn = $(this);
                    var direction = _btn.hasClass('order_down') ? 'down': 'up';
                    var fuid = parseInt(_btn.attr('uid'));
                    var noworder = parseInt(_btn.attr('order'));
                    var neworder = direction == 'down' ? (noworder + 1) : (noworder - 1);
                    that.changeOrder(fuid, noworder, neworder);
                } else {
                    xGame.toast.show(lang('only_leader_control_team'));
                }
            });
            //踢出队伍
            that.e.find('#elitepop_right_in').undelegate('.delete_btn', 'touchend').delegate('.delete_btn', 'touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                if (that.teamdetail && that.teamdetail.leader == player.info.uid) {
                    var fuid = $(this).attr('uid');
                    if (fuid > 0) {
                        that.fireMember(fuid);
                    }
                } else {
                    xGame.toast.show(lang('only_leader_kick'));
                }
            });
            //开战
            that.e.find('#elitepop_fight_btn').unbind('touchend').bind('touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                if (that.teamdetail && that.teamdetail.leader == player.info.uid) {
                    that.startFight();
                } else {
                    xGame.toast.show(lang('only_leader_begin_battle'));
                }
            });
            //购买次数
            that.e.find('#elitepop_buy_btn').unbind('touchend').bind('touchend',
            function() {
                if (that.islock == true) {
                    return false;
                }
                that.tempLock();

                that.buytimesConfirm();
            });

            that.teamScroller = null;

        },

        bindSocket: function() {
            var that = this;

            /*** 绑定事件 ***/
            //测试消息
            that.socket.on('testmsg',
            function(msg) {
                alert(msg);
            });
            //有用户进入副本
            that.socket.on('userin',
            function(msg) {
                console.log(msg);
            });
            //有用户离开副本
            that.socket.on('userlogout',
            function(msg) {
                console.log(msg);
            });
            /* 断线 */
            that.socket.on('disconnect',
            function(msg) {
                that.hide(true);
                xGame.toast.show(lang('you_have_leave_team'));
            });
            //登录结果
            that.socket.on('loginstatus',
            function(msg) {
                if (msg == 1) { //登录成功
                    console.log('成功已进入副本');
                    //获取多人副本首页数据
                    that.socket.emit('eliteindex', '');
                } else {
                    var notice = '';
                    switch (msg) {
                    case - 1 : notice = lang('login_fail_lost');
                        break;
                    case - 2 : notice = lang('login_fail_');
                        break;
                    case 0:
                        notice = lang('lost_login_status');
                        break;
                    default:
                        notice = lang('can_not_join_elite');
                        break;
                    }
                    xGame.toast.show(notice);
                    that.hide(true);
                }

            });
            //创建队伍成功
            that.socket.on('createteamdone',
            function(msg) {
                console.log(msg);
            });
            //创建队伍失败
            that.socket.on('createteam_error',
            function(msg) {
                if (msg == -1) {
                    xGame.toast.show(lang('create_team_fail'));
                } else if (msg == -2) {
                    xGame.toast.show(lang('no_country_to_create_team'));
                } else if (msg == -3) {
                    xGame.toast.show(lang('get_team_info_fail'));
                } else {
                    xGame.toast.show(lang('you_had_in_team_fail'));
                    console.log(msg);
                }
            });
            //加入队伍成功
            that.socket.on('jointeamdone',
            function(msg) {
                console.log(msg);
            });
            //加入队伍失败
            that.socket.on('jointeam_error',
            function(msg) {
                var notice = '';
                switch (msg) {
                case 0:
                    notice = lang('you_had_in_team_fail');
                    break;
                case - 1 : notice = lang('team_not_exist');
                    break;
                case - 2 : notice = lang('team_is_dissolve');
                    break;
                case - 3 : notice = lang('team_is_full');
                    break;
                case - 4 : notice = lang('you_have_no_country');
                    break;
                case - 5 : notice = lang('your_team_and_country_fault');
                    break;
                default:
                    notice = lang('join_team_fail');
                    break;
                }
                xGame.toast.show(notice);
            });
            //退出队伍成功
            that.socket.on('exitteamdone',
            function(msg) {
                console.log(msg);
                that.cleanTeamDetail();
            });
            //退出队伍失败
            that.socket.on('exitteam_error',
            function(msg) {
                var notice = '';
                switch (msg) {
                case 0:
                    notice = lang('exit_team_fail_no_team');
                    that.cleanTeamDetail();
                    break;
                case - 1 : notice = lang('exit_team_fail_no_country');
                    that.cleanTeamDetail();
                    break;
                case - 2 : notice = lang('team_is_dissolve');
                    that.cleanTeamDetail();
                    break;
                case - 3 : notice = lang('leader_exit_fail');
                    break;
                }
                xGame.toast.show(notice);
            });
            //踢人成功
            that.socket.on('firememberdone',
            function(msg) {
                console.log(msg);
            });
            //踢人失败
            that.socket.on('firemember_error',
            function(msg) {
                var notice = '';
                switch (msg) {
                case 0:
                    notice = lang('kick_out_people_fail1');
                    break;
                case - 1 : notice = lang('kick_out_people_fail2');
                    break;
                case - 2 : notice = lang('kick_out_people_fail3');
                    break;
                case - 3 : notice = lang('kick_out_not_leader');
                    break;
                case - 4 : notice = lang('kick_out_not_in_team');
                    break;
                }
                xGame.toast.show(notice);
            });
            //被踢出队伍
            that.socket.on('beenfire',
            function(msg) {
                xGame.toast.show(lang('you_are_kicked_out_team'));
                console.log(msg);
                that.cleanTeamDetail();
            });
            //解散队伍成功
            that.socket.on('fireteamdone',
            function(msg) {
                //xGame.toast.show('队伍解散成功！');
                console.log(msg);
                that.cleanTeamDetail();
            });
            //队伍被解散
            that.socket.on('teambeenfire',
            function(msg) {
                xGame.toast.show(lang('your_team_is_dissolve'));
                console.log(msg);
                that.cleanTeamDetail();
            });
            //解散队伍失败
            that.socket.on('fireteam_error',
            function(msg) {
                var notice = '';
                switch (msg) {
                case 0:
                    notice = lang('dissolve_fail1');
                    that.cleanTeamDetail();
                    break;
                case - 1 : notice = lang('dissolve_fail2');
                    that.cleanTeamDetail();
                    break;
                case - 2 : notice = lang('team_is_dissolve');
                    that.cleanTeamDetail();
                    break;
                case - 3 : notice = lang('only_leader_can_dissolve');
                    break;
                }
                xGame.toast.show(notice);
            });

            //获取多人副本首页数据：我开启的战场列表、剩余次数等
            that.socket.on('eliteindex',
            function(msg) {

                //战场列表
                var html = '';
                var n = 0;
                var firstField = null; //第一个军团
                var myField = null; //默认军团（默认第一个，可以通过聊天室里带过来）
                var allclose = true; //是否全部都没有开启
                var j = 0; //记录当前能打到的最高副本;
                for (var i = 0; i < msg.list.length; i++) {
                    if(msg.list[i].open == 1) {
                        j++;
                    }

                    html += '<li id="field' + msg.list[i].fid + '" fid="' + (msg.list[i].open == 1 ? msg.list[i].fid: 0) + '" fieldname="' + msg.list[i].name + '"><img src="' + that.getFieldImage(msg.list[i].fid) + '" />' + (msg.list[i].open == 0 ? '<p class="cover"></p>': '') + '</li>';
                    n++;

                    if (msg.list[i].open == 1 && allclose == true) {
                        allclose = false;
                    }

                    if (i == 0) {
                        firstField = {
                            fid: msg.list[i].fid,
                            name: msg.list[i].name
                        };
                    }
                    //如果有目标队伍，并且自己的国家与目标队伍一致
                    if (that.target != null && that.target.fid == msg.list[i].fid && msg.list[i].open == 1 && (that.target.countryid == 0 || that.target.countryid == player.info.country)) {
                        myField = {
                            fid: msg.list[i].fid,
                            name: msg.list[i].name
                        };
                    }
                }

                //没有一个开启，不让进
                if (allclose) {
                    that.hide(true);
                    xGame.toast.show(lang('through_%x_enter_map', [lang('yanliang')]));
                    return false;
                }

                //优化多人副本列表显示
                var my = 0;
                if(j && j > 4) {
                    console.log(j);
                    console.log(i);
                    var max = (210 * msg.list.length - 320) * -1;
                    my = (210 * j) * -1;
                    my = my < max ? max : my;
                    my = my + 560;
                    console.log('scrollto ' + my);
                }

                that.e.find('#elite_list_scroller').html(html);
                that.fScroll = xGame.scroller('elitepop_scroller', n * 210, true, my);

                //更新今日挑战次数
                that.updateTimes(msg.number);
                //更新至尊组队价格和加成等信息
                that.updateTopteamPrice(msg.supper_group);

                /* 初始化 */
                that.teamdetail = null; //初始化当前team信息
                if (myField == null) {
                    that.target = null;
                    that.fid = firstField.fid; //默认第一个军团
                    that.fieldname = firstField.name; //第一个军团的名字
                    that.fetchTeamList(that.fid); //开始刷新列表
                } else { //外部来的
                    that.fid = myField.fid;
                    that.fieldname = myField.name;
                    that.fetchTeamList(that.fid, that.target.teamid, that.target.countryid); //开始刷新列表
                }
                //显示当前
                $('#field' + that.fid).addClass('now');

                //显示界面，隐藏loading
                that.e.show();
                $('#worldloading').hide();

            });
            //获取多人副本首页数据失败
            that.socket.on('eliteindex_error',
            function(msg) {
                xGame.toast.show(lang('get_team_index_fail') + msg);
                that.hide(true);
                $('#worldloading').hide();
            });
            //拿到当前军团的队伍信息
            that.socket.on('teamlist',
            function(data) {
                if (that.background == true || data.id != that.fid) { //在后台 || 不是我当前查看的军团下的信息，不更新(这里需要注意一下，后端最后能够只给在当前军团的人发送消息)
                    return;
                }
                that.teamlistData.push(data);
                //that.buildTeamList(data);
            });
            //拿到当前军团的队伍信息 失败
            that.socket.on('teamlist_error',
            function(msg) {
                xGame.toast.show(lang('get_team_info_fail') + msg);
            });
            //拿到队伍详情
            that.socket.on('getteamdone',
            function(msg) {
                that.buildTeamdetail(msg);
            });
            //队伍改变位置错误
            that.socket.on('changeorder_error',
            function(msg) {
                var notice = '';
                switch (msg) {
                case 0:
                    notice = lang('team_fail1');
                    break;
                case - 1 : notice = lang('team_fail2');
                    break;
                case - 2 : notice = lang('team_is_dissolve');
                    break;
                case - 3 : notice = lang('only_team_can_change_site');
                    break;
                }
                xGame.toast.show(notice);
            });
            /* 开战失败 */
            that.socket.on('startfight_error',
            function(msg) {
                var notice = '';
                switch (msg) {
                case 0:
                    notice = lang('team_fail1');
                    break;
                case - 1 : notice = lang('team_is_dissolve');
                    break;
                case - 2 : notice = lang('only_leader_begin_battle');
                    break;
                case - 3 : notice = lang('only_3_begin_battle');
                    break;
                case - 4 : notice = lang('team_gold_not_enough', [that.topTeamPrice]);
                    break;
                case - 5 : notice = lang('join_battle_later');
                    break;
                case - 7 : notice = lang('only_vip8_create_team');
                    break;
                default:
                    notice = lang('join_battle_later');
                    break;
                }
                xGame.toast.show(notice);
                $('#ajaxloading').hide();
            });
            //队长发起战斗后，队员们准备开战
            that.socket.on('readyfight',
            function(msg) {
                console.log('readyfight');
                $('#ajaxloading').show();
            });
            that.socket.on('readyfight_error',
            function(msg) {
                console.log('readyfight_error');
                $('#ajaxloading').hide();
            });
            /* 开始战斗 */
            that.socket.on('enterfight',
            function(msg) {

                $('#ajaxloading').hide();

                if (msg.status == 1) {
                    if (typeof chatpop != 'undefined' && chatpop.e != null) {
                        chatpop.hide();
                    }
                    //更新次数
                    that.updateTimes(msg.info[player.info.uid].number);

                    //将多人副本放入后台运行，不中断连接
                    that.background = true;
                    that.e.hide();
                    //附加信息
                    msg.attackor = that.teamdetail.name;
                    msg.defenceor = that.fieldname;
                    //进入战斗
                    xGame.loadWorld('elitebattle', msg);
                    //直接删除自己队伍的DOM
                    $('#team' + that.teamid).remove();
                    //清理自己的队伍信息
                    that.cleanTeamDetail();
                } else if (msg.status == -2 && msg.uid == player.info.uid) {
                    xGame.toast.show(lang('no_times_battle'));
                } else if (msg.status == -2 && msg.uid != player.info.uid) {
                    var _name = ''; //获取缺次数的那个可怜孩子的名字
                    for (var i = 0; i < that.teamdetail.list.length; i++) {
                        if (that.teamdetail.list[i].uid == msg.uid) {
                            _name = that.teamdetail.list[i].nickname;
                        }
                    }
                    xGame.toast.show(lang('no_times_battle', [_name]));
                } else {
                    xGame.toast.show(lang('battle_id_error') + msg.status);
                }
            });

        },

        getFieldImage: function(fid) {
            var fid = parseInt(fid);
            if(fid <= 50) {
                return staticUrl + 'img/pop/elitepop_field' + fid + '.jpg';
            } else {
                return 'http://img2.hanjiangsanguo.com/hjimg/pop/elitepop_field' + fid + '.jpg';
            }
        },

        buildTeamdetail: function(data) {
            var that = this;
            that.teamid = data.teamid;
            that.teamdetail = data;
            /*
            if(data.length > 0 && helper.compArray(data, that.teamlist)) {//列表没有变化，则不刷新
                return false;
            }*/

            that.e.find('#elitepop_right_out').hide();
            var _dom = that.e.find('#elitepop_right_in');
            _dom.show();

            //按钮
            if (data.leader == player.info.uid) { //是队长
                _dom.find('#elitepop_fire_btn').show();
                _dom.find('#elitepop_fight_btn').show();
                _dom.find('#elitepop_exit_btn').hide();
                if (data.invited) {
                    _dom.find('#elitepop_invite_btn').hide();
                } else {
                    _dom.find('#elitepop_invite_btn').show();
                }
            } else {
                _dom.find('#elitepop_exit_btn').show();
                _dom.find('#elitepop_fire_btn').hide();
                _dom.find('#elitepop_fight_btn').hide();
            }

            /* 队伍内成员列表 */
            //1. 整理顺序
            var newlist = {};
            for (var i = 0; i < data.list.length; i++) {
                newlist[data.list[i].order] = data.list[i];
            }

            //2. 显示列表
            var html = '';
            for (var i = 1; i <= 3; i++) {
                if (typeof newlist[i] == 'undefined') { //空闲位置
                    html += '<li><p class="wait">' + lang('waiting') + '</p></li>';
                    continue;
                }

                html += '<li>';
                if (typeof newlist[i].leader != 'undefined' && newlist[i].leader == true) { //队长标志
                    html += '<span class="leader"></span>';
                } else if (this.teamdetail.leader == player.info.uid) { //普通成员的删除按钮（仅队长能看到）
                    html += '<button class="btn delete_btn" uid="' + newlist[i].uid + '"></button>';
                }
                html += '<p class="name">' + newlist[i].nickname + '</p>';
                html += '<p class="lv">Lv.' + newlist[i].level + '</p>';
                if (this.teamdetail.leader == player.info.uid) { //仅队长能够看到向上向下
                    if (i != 1) { //第1个位置不能显示向上，并且如果是第3个位置，要位置改变一下
                        html += '<button class="btn order_btn ' + (i == 3 ? 'up_right': '') + '" uid="' + newlist[i].uid + '" order="' + i + '"></button>';
                    }
                    if (i != 3) { //第3个位置不能显示向下
                        html += '<button class="btn order_btn order_down" uid="' + newlist[i].uid + '" order="' + i + '"></button>';
                    }
                }
                html += '</li>';

            }

            _dom.find('ul').html(html);
            _dom = null;

        },

        /* 构建队伍列表 */
        buildTeamList: function(data) {
            var that = this;

            var list = data.list;
            if (list.length > 0 && helper.compArray(list, that.teamlist)) { //列表没有变化，则不刷新
                return false;
            }
            var html = '';
            for (var i in list) {
                html += '<li id="team' + list[i].teamid + '">';
                html += '<p class="name ' + (list[i].teamid == that.teamid ? 'now': '') + '">' + list[i].name + (list[i].topteam ? '<span>' + lang('zhizun') + '</span>': '') + '</p>';
                html += '<p class="num">' + lang('team_num') + '<span>' + list[i].num + '</span>/3</p>';
                html += '<p class="allow">' + (list[i].countryid == '0' ? lang('all_can_join') : lang('country') + ':' + list[i].countryname + '') + '</p>';
                html += that.teamid > 0 ? '': '<button class="btn pop_btn_middle" countryid="' + list[i].countryid + '" teamid="' + list[i].teamid + '">' + lang('join') + '</button>';
                html += '</li>';
            }

            that.e.find('#elitepop_left').find('ul').html(html);
            
            if (html == '') {
                that.e.find('#elitepop_left').addClass('nodata');
            } else {
                that.e.find('#elitepop_left').removeClass('nodata');
            }
            
            if (that.teamScroller == null) {
                that.teamScroller = new iScroll('elitepop_left', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: true
                });
            }
            that.teamScroller.refresh();

            //组装该关卡奖励
            if (parseInt(data.firstkill) == 1) {
                if (typeof data.first_exp != 'undefined' && typeof data.firstitemname != 'undefined') {
                    that.e.find('#elitepop_right_out').find('.reward_exp').find('span').html('+' + data.first_exp);
                    that.e.find('#elitepop_right_out').find('.reward_item').find('span').css('color', helper.getItemColor(data.firstquality)).html(data.firstitemname);
                    that.e.find('#elitepop_right_out').find('.firstkill').show();
                    that.e.find('#elitepop_right_out').find('.reward_item').find('em').html(lang('elitepop_get')).css('display', 'inline');
                }
            } else {
                if (typeof data.exp != 'undefined' && typeof data.itemname != 'undefined') {
                    that.e.find('#elitepop_right_out').find('.reward_exp').find('span').html('+' + data.exp);
                    that.e.find('#elitepop_right_out').find('.reward_item').find('span').css('color', helper.getItemColor(data.quality)).html(data.itemname);
                    that.e.find('#elitepop_right_out').find('.firstkill').hide();
                    if ('getrate' in data) {
                        if (data.getrate == 0) {
                            that.e.find('#elitepop_right_out').find('.reward_item').find('em').html(lang('elitepop_probability')).css('display', 'none');
                        } else {
                            that.e.find('#elitepop_right_out').find('.reward_item').find('em').html(lang('elitepop_probability')).css('display', 'inline');
                        }
                    }
                }
            }
        },

        /* 登录 */
        loginServer: function() {
            if (this.socket) {
                this.socket.emit('login', {
                    uid: player.info.uid,
                    token: xGame.token,
                    serverid: xGame.serverid
                });
            }
        },

        /* 更改位置 */
        changeOrder: function(uid, from, to) {
            if (this.socket) {
                this.socket.emit('changeorder', {
                    id: this.fid,
                    teamid: this.teamid,
                    from: from,
                    to: to
                });
            }
        },
        /* 获取最新队伍列表 */
        fetchTeamList: function(fid, teamid, countryid) {
            var that = this;
            if (this.socket) {
                if (typeof fid == 'undefined') {
                    fid = this.fid;
                }
                this.socket.emit('teamlist', {
                    id: fid
                });

                /* 有外部进入的 */
                if (teamid > 0) {
                    $('#ajaxloading').show();
                    this.isTimeout.push(setTimeout(function() {
                        that.joinTeam(teamid, countryid);
                        $('#ajaxloading').hide();
                    },
                    500));
                }
            }
        },

        /* 创建队伍 */
        createTeam: function(type, topteam) {
            if (this.lefttimes <= 0) {
                xGame.toast.show(lang('no_times_to_create_team'));
                return false;
            }
            if (this.socket) {
                if (!type) {
                    type = 1;
                }
                if (!topteam) {
                    topteam = false;
                }
                //至尊组队，检查元宝
                if (topteam == true && player.info.gold < this.topTeamPrice) {
                    xGame.goPay(lang('team_gold_not_enough', [this.topTeamPrice]));
                    return false;
                }

                this.socket.emit('createTeam', {
                    id: this.fid,
                    type: type,
                    topteam: topteam
                });
            }
        },

        /* 加入队伍 */
        joinTeam: function(teamid, countryid) {
            if (this.lefttimes <= 0) {
                xGame.toast.show(lang('no_times_to_join_team'));
                return false;
            }
            console.log(teamid, countryid);
            if (typeof countryid != 'undefined' && countryid > 0) {
                if (player.info.country <= 0) {
                    xGame.toast.show(lang('join_team_fail2'));
                    return false;
                } else if (player.info.country != countryid) {
                    xGame.toast.show(lang('join_team_fail3'));
                    return false;
                }
            }
            if (this.socket) {
                this.socket.emit('jointeam', {
                    id: this.fid,
                    teamid: teamid
                });
            }
        },

        /* 退出/解散队伍 */
        exitTeam: function(isfire) {
            if (this.socket && this.teamid > 0) {
                this.socket.emit(isfire == true ? 'fireteam': 'exitteam', {
                    id: this.fid,
                    teamid: this.teamid
                });
                this.teamdetail = null;
            }
        },

        /* 踢某人出去 */
        fireMember: function(uid) {
            if (this.socket && this.teamid > 0) {
                this.socket.emit('firemember', {
                    id: this.fid,
                    teamid: this.teamid,
                    uid: uid
                });
            }
        },

        /* 请求开战 */
        startFight: function() {
            if (this.socket) {
                this.socket.emit('startfight', 1);
                $('#ajaxloading').show();
            }
        },

        /* 清理自己当前所在队伍：用于退出队伍、队伍解散等情况 */
        cleanTeamDetail: function() {
            this.teamid = 0;
            this.teamdetail = null;
            this.e.find('#elitepop_right_in').hide();
            this.e.find('#elitepop_right_out').show();
        },

        /* 聊天邀请 TODO */
        chatInvite: function() {
            var that = this;

            if (that.teamdetail) {
                if (that.teamdetail.num >= 3) {
                    xGame.toast.show(lang('team_is_full'));
                    return false;
                }
                var render = function(res) {
                    if (res.status == 1) {
                        xGame.toast.show(lang('had_sent_to_chat'));
                        CD['chat'].read();
                        that.socket.emit('teaminvited', {
                            id: that.fid,
                            teamid: that.teamid
                        });
                        that.e.find('#elitepop_invite_btn').hide();
                    } else {
                        xGame.toast.show(lang('invite_fail'));
                    }
                };
                loadContents({
                    'c': 'elite',
                    'm': 'sendinvite',
                    'fid': that.fid,
                    'teamid': that.teamid,
                    'countryid': that.teamdetail.countryid
                },
                render);
                render = null;
            }
        },

        /* 更新次数 */
        updateTimes: function(times) {
            this.lefttimes = parseInt(times);
            this.e.find('#elitepop_lefttimes').find('span').html(this.lefttimes);
        },

        /* 更新至尊组队价格 */
        updateTopteamPrice: function(topteam) {
            //价格
            this.topTeamPrice = parseInt(topteam.gold);
            $('#topcreateteam_btn').html('x' + this.topTeamPrice);
            //加成
            $('#topteam_addvalue').html(lang('team_reward', [topteam.caption, topteam.player]));
        },

        /* 购买次数 价格确认框 */
        buytimesConfirm: function() {
            var that = this;

            var priceRender = function(res) {
                if (res.status == 1 && res.price > 0) {
                    xGame.confirm.show(lang('notice'), lang('team_buy_times_ask', [res.price, that.lefttimes]), 2,
                    function() {
                        that.buytimesProcess();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('buy'), lang('close'));
                } else {
                    xGame.toast.show(lang('can_not_buy_times_yet'));
                    return false;
                }
            };
            loadContents({
                'c': 'elite',
                'm': 'buy_number',
                't': 0
            },
            priceRender);
            priceRender = null;
        },

        /* 开始购买次数 */
        buytimesProcess: function() {
            var that = this;

            var buyRender = function(res) {
                if (res.status == 1) {
                    player.updateinfo({
                        "gold": res.all_gold
                    });
                    that.updateTimes(res.times);
                    $('#buy_arenatimes_price').html(res.price);
                    $('#buy_arenatimes').html(res.times);
                } else if (res.status == -1) {
                    xGame.goPay(lang('gold_not_enough_for_buying'));
                } else {
                    xGame.toast.show(lang('buy_fail_try_later'));
                }

            };
            loadContents({
                'c': 'elite',
                'm': 'buy_number',
                't': 1
            },
            buyRender);
            buyRender = null;
        },

        unbindAll: function() {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.find('#elitepop_fight_btn').unbind('touchend');
            this.e.find('#elitepop_buy_btn').unbind('touchend');
            this.e.find('#elitepop_top').undelegate('li', 'touchend');
            this.e.find('#elitepop_left').undelegate('.pop_btn_middle', 'click');
            this.e.find('#teamtype_wrap').undelegate('li', 'touchend');
            this.e.find('#elitepop_right_out').undelegate('#createteam_btn', 'touchend');
            this.e.find('#elitepop_right_out').undelegate('#topcreateteam_btn', 'touchend');
            this.e.find('#elitepop_right_out').undelegate('#elitepop_chat_btn2', 'touchend');
            this.e.find('#elitepop_right_in').undelegate('#elitepop_exit_btn', 'touchend');
            this.e.find('#elitepop_right_in').undelegate('#elitepop_fire_btn', 'touchend');
            this.e.find('#elitepop_right_in').undelegate('#elitepop_chat_btn', 'touchend');
            this.e.find('#elitepop_right_in').undelegate('#elitepop_invite_btn', 'touchend');
            this.e.find('#elitepop_right_in').undelegate('.order_btn', 'touchend');
            this.e.find('#elitepop_right_in').undelegate('.delete_btn', 'touchend');
            
        },

        //force 强制退出
        hide: function(force) {
            xGame.unlock();
            //队长退出要给提示
            if (this.teamdetail && this.teamdetail.leader == player.info.uid && typeof force == 'undefined') {
                xGame.confirm.show(lang('exit'), lang('exit_ask'), 2,
                function() {
                    xGame.confirm.hide(function() {
                        elitepop.hide(true);
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
                return false;
            }

            if (xGame.confirm.isshow == true) {
                xGame.confirm.hide(); //假如有弹窗提示，直接关闭
            }
            this.clearTeamlistTimer();
            this.teamlistData.length = 0;
            if (this.socket) { //断开连接
                this.socket.removeAllListeners();
                this.socket.disconnect();
                this.socket = null;
            }
            this.lefttimes = 0;
            this.fid = 0;
            this.teamid = 0;
            this.teamlist.length = 0;
            this.teamdetail = null;
            this.background = false;
            this.target = null;

            if (this.teamScroller != undefined) {
                this.teamScroller.destroy();
                this.teamScroller = null;
            }
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;

            $('#worldloading').hide();
            player.reloadInfo();
        },
    };
})();