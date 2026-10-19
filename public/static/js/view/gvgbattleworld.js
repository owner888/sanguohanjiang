/* 群服争霸战 */
var teamsprites = {};

(function() {
    gvgbattleWorldClass = function() {
        this.e = null;
        this.battle_log = null; //保存战斗数据

        this.leftScroller;
        this.rightScroller;
        this.leftDom;
        this.rightDom;
        this.leftData;//队伍列表数据，以uid为键
        this.rightData;//队伍列表数据，以uid为键

        this.atkData;//当前阵上攻击方的数据
        this.defData;//当前阵上防守方的数据

        this.winner;//结果页胜利方

        this.leftSaveData;//保存的非顺序的log数据，以uid为键，也可以说是左边发动换行战斗后的数据
        this.rightSaveData;//保存的非顺序的log数据，以uid为键，也可以说是右边发动换行战斗后的数据
        this.leftHeroWinList;//以英雄uid为键
        this.rightHeroWinList;//以英雄uid为键

        this.callback;//这个是新加入的参数，用来解决2次换行前一次换行战斗还没结束的问题
        this.callbackData;//回调的参数
        
        this.deleteD;//删除数据是要用到的方向
        this.deleteUid;//删除数据是要用到的uid
        this.deleteWin;//删除数据是要用到的连胜次数
        
        /*加入参数是为了两个换行时互相覆盖能够保存被覆盖的，然后让被覆盖的可以被调用*/
        this.saveCallback;//保存的callback;
        this.times;
        this.line;
        this.needData;
        /*回调需要的参数 因为去掉一些参数会比较麻烦，所以直接添加参数,
        以line_times为键，保存deleteD，deleteUid，deleteWin。
        */
        
        this.lineTeam;//按照三个线程排序，以line为键
        
        this.skip;
        this.timeoutFunc;
        
        this.allData;
        
        this.islast;//是否是最后一场战斗
        
        this.lineStatus;//当前线程的状态,以line未键，1为开启，0为销毁  
        this.isTimeout = [];  

    };

    gvgbattleWorldClass.prototype = {
        init: function(data) {
            
            this.leftScroller = null;
            this.rightScroller = null;
            this.leftData = {};
            this.rightData = {};

            this.callback = null;
            this.callbackData = null;
            
            this.saveCallback = null;
            this.needData = {};
            
            this.initPage();
            this.fight(data);
            

        },

        initPage: function(res) {
            var that = this;

            if (that.e != null) {
                return false;
            }

            $('#header').hide();
            $('#footer').hide();

            var dom_world = $('#world');

            dom_world.append($.template('gvgbattleworld_tmpl'));

            that.e = $('#gvgbattleworld_page');
            that.leftDom = that.e.find('#leftsider');
            that.rightDom = that.e.find('#rightsider');
            
            dom_world = null;
            
            that.e.undelegate('#skipbtn','click').delegate('#skipbtn','click',function(e){
                that.skip = true;
                clearTimeout(that.timeoutFunc);
                
                that.isTimeout.push(setTimeout(function() {
                    if (that.winner != null) {
                        miscpop.battleResultFromGvg(that.winner, that.islast);
                    } else {
                        xGame.loadWorld('gvg');
                    }
                } ,1000));
                e.preventDefault();
                e.stopPropagation();
            });
            
        },

        // 自己写的iscroll方法 type == 1 --> left type == 2 --> right
        spIscroll : function(i) {
            var arr = ['', 'left', 'right'];
            if (this[arr[i] + 'Scroller'] == null) {
                if (this[arr[i] + 'Data'].num > 10) {
                    this[arr[i] + 'Scroller'] = new iScroll('gvgbattle_' + arr[i], {
                        bounce: false,
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: true
                    });
                    this[arr[i] + 'Scroller'].refresh();
                }
            } else {
                if (this[arr[i] + 'Data'].num > 10) {
                    this[arr[i] + 'Scroller'].refresh();
                } else {
                    this[arr[i] + 'Scroller'].destroy();
                    this[arr[i] + 'Scroller'] = null;
                }
            }
        },
        
        fillTeamList : function(res) {
            var arr = ['left', 'right'];
            for (var i = 0;i < 2;i ++) {
                this[arr[i] + 'Dom'].find('.country').html(res[arr[i] + '_name']);
            }
            arr = null;
            this.buildLi(res);
        },

        buildLi : function(res) {
            var that = this;

            var fillList = ['left_team', 'right_team'];
            for (var i = 0; i < fillList.length; i++) {
                var _data = res[fillList[i]];
                //console.log(_data.length);
                if(_data.length != 0){
                    var html = '';
                    var classN = '';
                    for (var ii = 0; ii < _data.length ; ii++) {
                        
                        if (_data[ii].uid == player.info.uid) {
                            classN = ' me';
                        } else {
                            classN = '';
                        }
                        
                        html += '<li id="ui' + _data[ii]['uid'] + '" class="' + classN + '" uid="' + _data[ii].uid + '">' + _data[ii].nickname + '<span>Lv.' + _data[ii].level + '</span></li>';
                        if (fillList[i] == 'left_team') {
                            that.leftData[_data[ii]['uid']] = _data[ii];
                            that.leftData['num'] = _data.length;
                        } else {
                            that.rightData[_data[ii]['uid']] = _data[ii];
                            that.rightData['num'] = _data.length;
                        }
                    }
                    if (fillList[i] == 'left_team') {
                        that.leftDom.find('.scroller_content').html(html);
                        that.spIscroll(1);
                    } else {
                        that.rightDom.find('.scroller_content').html(html);
                        that.spIscroll(2);
                    }
                }
            }
        },

        fight: function(data) {
            this.allData = data;
            
            this.fillTeamList(data);
            this.buildBattlefield(data);
            this.winner = data.battle.win == 1 ? data.left_name : data.right_name;//1左边赢2右边赢
            this.islast = data.round == 1 ? true : false;
        },

        buildBattlefield: function(res) {
            var that = this;

            //1.先初始化战场
            teamsprites = {};
            for (var i in res.battle.battle_ready) { //i 每一横条的数据
                //每一列的数据
                var linedata = res.battle.battle_ready[i];
                //console.log(linedata);
                //左右两边
                var teams = ['left', 'right'];
                for (var ii = 0; ii < teams.length; ii++) { //每一横条的左右
                    var direction = teams[ii]; //我的方位
                    var mydata = linedata[direction]; //我的数据

                    if (mydata) {
                        that._createTeam({
                            direction: direction,
                            line: i,
                            teamid: direction + i,
                            uid: mydata['uid'],
                            name: that[direction + 'Data'][mydata['uid']].nickname,
                            level: 50,
                            blood: 1,
                            //初始化的血，是假的
                            allblood: 1,
                            matrix: mydata['matrix']
                        });

                        that.clearLiByUid(direction, mydata['uid']);
                    }
                }
            }

            //2.保存战斗数据
            that.battle_log = res.battle.battle_log;

            that.isTimeout.push(setTimeout(function() {

                //保存三个攻击方以及防守方的数据
                that.initThreeData(res.battle.battle_ready);
                //保存换行后的数据
                that.beforeAttack();
                
                that.lineStatus = {                    
                    1: 1,                    
                    2: 1,                    
                    3: 1                
                }                                
                that.getLineTeam();
                //console.log('得到的lineTeam -----> ');
                //console.log(that.lineTeam);
                
                that.isTimeout.push(setTimeout(function() {
                    if (that.e == null) {
                        return false;
                    }
                    var skipBtn = '<button class="btn skipbtn1" id="skipbtn">' + lang('skip_battle_2') + '</button>';
                    that.rightDom.find('#gvgbattle_right').height('395px');
                    if (that.rightScroller != null) {
                        that.rightScroller.wrapperH = 395;
                        that.rightScroller.refresh();
                    }
                    that.rightDom.append(skipBtn);
                    skipBtn = null;
                },5000));

                //记录每组的动画播放位置，从0开始
                that.attackProcess(0, 1);
                that.attackProcess(0, 2);
                that.attackProcess(0, 3);

                that.skip = false;
            },
            500));
            
            $('#worldloading').removeClass('black').hide();
        },
        
        //简单获得每行的数据
        getLineTeam: function() {
            var that = this;
            that.lineTeam = {
                '1' : {},
                '2' : {},
                '3' : {}
            };
            var temp;
            var obj;
            var arr = ['leftSaveData','rightSaveData'];
            for (var i = 0; i < 2; i ++) {
                obj = that[arr[i]];
                for (var j in obj) {
                    if (obj[j] != undefined) {
                        for (var k = 0;k < 8; k ++) {
                            if (obj[j][k] != undefined) {
                                temp = obj[j][k];
                                //console.log(temp);
                                that.lineTeam[temp.line][temp.times] = j;
                            }
                        }
                    }                   
                }
            }
            temp = obj = null;
        },
        
        //通过传进来的line,time判断是否是最早执行的换行战斗，返回true就是没有，false就是有
        noEarlyBattle: function(line, time) {
            //console.log('传进来的line = ' + line + ' ,time = ' + time);
            var that = this;
            var back = true;
            for (var i = time - 1; i >= 0; i --) {
                if (that.lineTeam[line][i] != undefined) {
                    console.log('有早的换行战斗！');
                    back = false;
                    break;
                }
            }
            return back;
        },
        
        //这里计算左右两边的赢的次数，获得不是连续的数据，再经过一轮筛选，最终获得换行后的数据
        beforeAttack: function() {
            var that = this;
            that.leftSaveData = {};//左边保存的数据
            that.rightSaveData = {};//右边保存的数据
            that.leftHeroWinList = {};//左边连胜数据
            that.rightHeroWinList = {};//右边连胜数据
            var direction = ['left','right'];
            var d;//当前方向
            var uid;//uid
            var temp;//缓存数据
            var leftInterruptData = {};//左边的非连续数据
            var rightInterruptData = {};//右边的非连续数据
            var leftTemp = [];
            var rightTemp = [];
            var Func = function(i) {
                if(that.battle_log[i] == undefined) {
                    //console.log('计算结束，发动换行操作的人，结果如下');
                    //console.log('left ----->');
                    //console.log(that.leftHeroWinList);
                    //console.log('左边的非连续数据 ----->');
                    //console.log(leftInterruptData);
                    //console.log(leftTemp);
                    if (leftTemp.length != 0) {//如果筛选出来了
                        for (var n = leftTemp.length - 1;n >= 0;n --) {
                            that.leftSaveData[leftTemp[n]] = leftInterruptData[leftTemp[n]];
                        }
                    }
                    //console.log(that.leftSaveData);
                    //console.log('right ----->');
                    //console.log(that.rightHeroWinList);
                    //console.log('右边的非连续数据 ----->');
                    //console.log(rightInterruptData);
                    //console.log(rightTemp);
                    if (rightTemp.length != 0) {//如果筛选出来了
                        for (var n = rightTemp.length - 1;n >= 0;n --) {
                            that.rightSaveData[rightTemp[n]] = rightInterruptData[rightTemp[n]];
                        }
                    }
                    //console.log(that.rightSaveData);
                    direction = null;
                    d = null;//当前方向
                    uid = null;//uid
                    temp = null;//缓存数据
                    leftInterruptData = null;//左边的非连续数据
                    rightInterruptData = null;//右边的非连续数据
                    leftTemp = null;
                    rightTemp = null;
                    return false;
                }
                //这个地方重写，重新梳理一下思路；
                //我去，这里的bug原来是换行之后输了，我以前一直按会赢做的
                //这个方法会多存一些数据，但是影响
                //三条线,两个方向
                for (var j = 1; j < 4; j ++) {
                    for (var k = 0; k < 2; k ++) {
                        temp = that.battle_log[i][j];//每一步的三条数据，可能为undefined
                        d = direction[k];//方向
                        if (temp != undefined) {//有数据时,先不排除换行
                            if (temp[d + '_win'] != undefined) {
                                if (temp[d + '_win'] >= 0) {//如果大于0，存起来
                                    uid = temp[d].uid;//获得赢的那方的uid
                                    if (that[d + 'HeroWinList'][uid] == undefined) {//没有数据的时候，默认是1连胜
                                        that[d + 'HeroWinList'][uid] = {};
										that[d + 'HeroWinList'][uid]['win'] = temp[d + '_win'];
										that[d + 'HeroWinList'][uid]['line'] = j;
                                    } else {//已经有数据了，判断是否是连续的
                                        if (that[d + 'HeroWinList'][uid]['win'] == 0) {//如果一直都是0，排除
                                            continue;
                                        }
                                        if (temp[d + '_win'] == that[d + 'HeroWinList'][uid]['win'] + 1 && that[d + 'HeroWinList'][uid]['line'] == j) {//是连续的，保存winlist
                                            that[d + 'HeroWinList'][uid]['win'] = temp[d + '_win'];
                                        } else {//不是连续的，保存savedata
                                            //console.log('不是连续的数据如下');
                                            //console.log(d + '的 uid = ' + uid + ',之前连胜是 ' + that[d + 'HeroWinList'][uid] + ' ,连胜变为 ' + temp[d + '_win']);
                                            if (d == 'left') {
                                                if(leftInterruptData[uid] == undefined) {//不存在时创建
                                                    leftInterruptData[uid] = {};
                                                }
                                                //保存的数据uid下面以连胜次数为键
                                                if (temp[d + '_win'] != 0) {
                                                    leftInterruptData[uid][temp[d + '_win']]= {
                                                        'line' : j ,
                                                        'times' : i
                                                    };
                                                } else {//强制将输了变成7连胜
                                                    leftInterruptData[uid][7]= {
                                                        'line' : j ,
                                                        'times' : i
                                                    };
                                                }
                                            } else {
                                                if(rightInterruptData[uid] == undefined) {//不存在时创建
                                                    rightInterruptData[uid] = {};
                                                }
                                                //保存的数据uid下面以连胜次数为键
                                                rightInterruptData[uid][temp[d + '_win']]= {
                                                    'line' : j ,
                                                    'times' : i
                                                };
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if (that.battle_log[i][j] != undefined && that.battle_log[i][j].status == 1) {
                        //console.log('查到是换行操作，因此应该向上追溯,从第' + i + '条数据开始');
                        for(var m = i; m >= 0;m --) {
                            if (that.battle_log[m][j] != undefined && that.battle_log[m][j]['left_win'] != undefined) {//如果找到一个不是不存在的
                                if (that.battle_log[m][j]['left_win'] > that.battle_log[m][j]['right_win']) {
                                    leftTemp.push(that.battle_log[m][j].left.uid);
                                    break;
                                } else if (that.battle_log[m][j]['right_win'] > that.battle_log[m][j]['left_win']) {
                                    rightTemp.push(that.battle_log[m][j].right.uid);
                                    break;
                                }
                            }
                        }
                    }
                }
                Func(i + 1);
            };
            Func(0);
        },

        //保存三个攻击方以及防守方的数据
        initThreeData : function(data) {
            //初始化
            this.atkData = {};
            this.defData = {};

            //console.log(data);

            for(var i = 1; i < 4; i ++){
                if(data[i] != undefined){
                    if(data[i].left != undefined){
                        this.atkData[i] = data[i].left.uid;
                    } else {
                        this.atkData[i] = '0';
                    }

                    if(data[i].right != undefined){
                        this.defData[i] = data[i].right.uid;
                    } else {
                        this.defData[i] = '0';
                    }
                }
            }
        },
        //检查是否是换行战斗，返回true就是，返回false就不是
        isTurnBattle: function(data,line,times){
            var that = this;
            if (that.leftSaveData[data.left.uid] != undefined || that.rightSaveData[data.right.uid] != undefined) {
                //console.log('起码是这个人');
                var temp = (that.leftSaveData[data.left.uid] != undefined ? that.leftSaveData[data.left.uid] : that.rightSaveData[data.right.uid]);
                //console.log(temp);
				//最接近的数据
				var win_times = (that.leftSaveData[data.left.uid] != undefined ? data.left_win : data.right_win);
                //选出最接近的连胜数据进行比较
                //因为强制设置为7了，所以这里也设置为7
                win_times = win_times == 0 ? 7 : win_times;
                for (var i = 0;i < 8;i ++) {
                    if (temp[i] != undefined && i == win_times) {
                        temp = temp[i];
                        break;
                    }
                }
                //console.log(temp.times);
                //console.log(temp.line);
                if (temp.line == line && temp.times == times) {
                    console.log('现在进行判断的是' + line + '号线程');
                    console.log('就是这条数据!!!!!!!!!!!!!!!!!!');
                    return true;
                } else {
                    //console.log('不是这条数据,这个人' + data.left.uid + '的times应该是' + temp.times + ',line应该是' + temp.line);
                    return false;
                }
            } else {
                //console.log('不是这个人，根本不可能');
                return false;
            }
        },

        //战斗进程
        attackProcess: function(times, line) {
            var that = this;
            
            if (that.skip) {
                return false;
            }
            if (that.battle_log == null) {
                return false;
            }
            if (that.battle_log[times] != undefined ) {
                if(that.battle_log[times][line] != undefined && that.battle_log[times][line].status == undefined) {//说明还有这一列
                    //放在这里才有用
                    that.times = times;
                    that.line = line;
                    //进入战斗线程，具体操作在战斗线程里面
                    //正常战斗时，检查是否是换行战斗
                    if (that.isTurnBattle(that.battle_log[times][line],line,times)) {//是换行战斗
                        console.log(times + '_' + line);
                        if (that.callback != undefined) {
                            if (line == that.callbackData.line && times == that.callbackData.times) {
                                console.log('只有当行数也对步数也对的时候才调用callback');
                                that.callback();
                                return false;
                            }
                        }
                        //如果回调也没有了但是有保存老回调的话，运行老回调
                        if (that.callback == undefined && that.saveCallback != undefined) {
                           that.saveCallback();
                           return false;
                        }
                        
                        if (that.callback == undefined && that.saveCallback == undefined) {                            
                            console.log('是换行战斗，但是由于之前有人在战斗所以没有存起来');                            
                        }   
                        
                        console.log('暂停进程，或者可以理解为line ' + line + ' 这条进程销毁了');
                        that.lineStatus[line] = 0;                        
                        return false;
                    } else {//不是换行战斗
                        console.log('正常战斗');
                        // if (times != 0 && that.atkData[line] != '0' && that.defData[line] != '0') {
                        //     console.log('正常战斗,以前没有判断的，现在加上判断，如果有人在战斗就暂停');
                        //     return false;
                        // }
                        that.attackThread(that.battle_log[times][line], times, line);
                    }
                } else if(that.battle_log[times][line] != undefined && that.battle_log[times][line].status == 1) {

                    console.log('换行战斗,现在准备换的是第' + line + '行');

                    //关键函数，这里应该开始合并战斗进程了，
                    //因为之前的已经暂停了，所以这一条进程就过去代替那一条进程了
                    //这里要记得把找到的数据删除掉，因为如果不删除可能又会被判断成为换行战斗
                    var temp;
                    for(var i = times;i >= 0;i --){
                        if(that.battle_log[i][line] != undefined && that.battle_log[i][line].status == undefined) {
                            temp = that.battle_log[i][line];
                            break;
                        }
                    }
                    //console.log(temp);
                    //获得uid
                    var uid = (parseInt(temp.left_win) > 0 ? temp.left.uid : temp.right.uid);
                    console.log('换行发起人uid = ' + uid);
                    //获得发起人保存的最小的连胜数，获得之后拿来用，用完之后删掉;
                    temp = (that.leftSaveData[uid] != undefined ? that.leftSaveData[uid] : that.rightSaveData[uid]);
                    //左边或者右边保存数据里
                    //console.log(temp);
                    if (temp != undefined) {
                        for (var i = 0;i < 8;i ++) {
                            if (temp[i] != undefined) {
                                temp = temp[i];
                                if (that.leftSaveData[uid] != undefined) {
                                    that.deleteD = 'leftSaveData';//删除数据是要用到的方向
                                    //delete that.leftSaveData[uid][i];
                                } else {
                                    that.deleteD = 'rightSaveData';//删除数据是要用到的方向
                                    //delete that.rightSaveData[uid][i];
                                }
                                that.deleteUid = uid;;//删除数据是要用到的uid
                                that.deleteWin = i;//删除数据是要用到的连胜次数
                                break;
                            }
                        }
                        that.needData[temp.line + '_' + temp.times] = {
                            'd' : that.deleteD,
                            'uid' : that.deleteUid,
                            'win' : that.deleteWin
                        };
                            
                        //temp现在是最小的数据了
                        //console.log(temp);
                        //如果没有更早的战斗并且有一个位置是空的，直接上去，否则的话就设置callback
                        if (that.noEarlyBattle(temp.line, temp.times)) {
                            /*2013年6月19号发现这里会有bug 
                            原因是这里虽然前面没有更早的战斗的，但是它准备换上去的人刚好空出来                                
                            还没来得及上阵，然后因为这里的判断导致该上阵的人上不去，                                
                            这里判断得修改，这里应该加一个判断此线程是否是0                            
                            */                            
                            console.log('换行战斗可以直接上的times = ' + temp.times + '!!!!!!!!!!');                            
                            if ((that.atkData[temp.line] == '0' || that.defData[temp.line] == '0') && that.lineStatus[temp.line] == 0) {
                                console.log('换行战斗可以直接上');
                                //放在这里就能保证是真正战斗的时候才删除了
                                that.cleanTeam('left' + line);
                                that.cleanTeam('right' + line);
                                that.atkData[line] = '0';
                                that.defData[line] = '0';
                                
                                //删除放在后面就不会有两次调用的问题了
                                delete that[that.deleteD][that.deleteUid][that.deleteWin];
                                delete that.lineTeam[temp.line][temp.times];
                                //直接上的战斗做特殊处理，直接调用正常战斗
                                that.attackThread(that.battle_log[temp.times][temp.line], temp.times, temp.line);
                                
                                return false;
                            } else {
                                console.log('跑到这里是没有更早的回到，可以安心做回调不用判断');
                                console.log('换行战斗需要做回调处理,当前位置为' + line + '，应该要到 第' + temp.times + '步第' + temp.line + '的时候再回调');
                                //如果已经有了回调并且回调新回调比老回调早一点出现，将老回调保存起来
                                //这些放到回调里就对了
                                that.callbackData = temp;
                                that.callback = function () {
                                    console.log('回调运行成功了');
                                    //console.log(that.line);
                                    //console.log(that.times);
                                    //console.log(that[that.deleteD][that.deleteUid][that.deleteWin]);
                                    var obj = that.needData[that.callbackData.line + '_' + that.callbackData.times];
                                    //console.log(obj);
                                    //console.log('回调中该删除的数据如下');
                                    //console.log(that[obj.d][obj.uid][obj.win]);
                                    //放在这里就能保证是真正战斗的时候才删除了
                                    delete that[obj.d][obj.uid][obj.win];
                                    delete that.lineTeam[that.callbackData.line][that.callbackData.times];
                                    that.cleanTeam('left' + line);
                                    that.cleanTeam('right' + line);
                                    that.atkData[line] = '0';
                                    that.defData[line] = '0';
                                    //这里执行完了一定要设置为空的才可以啊，不然会死循环的
                                    that.callback = null;
                                    //直接上的战斗做特殊处理，直接调用正常战斗
                                    that.attackThread(that.battle_log[that.callbackData.times][that.callbackData.line], that.callbackData.times, that.callbackData.line);
                                };
                            }
                            
                        } else {
                            console.log('跑到这里代表有更早的回调，需要处理回调的先后顺序');
                            console.log('换行战斗需要做回调处理,当前位置为' + line + '，应该要到 第' + temp.times + '步第' + temp.line + '的时候再回调');
                            //如果已经有了回调并且回调新回调比老回调早一点出现，将老回调保存起来
                            if (that.callback != undefined && temp.times > that.callbackData.times) {
                                console.log('将这次回调保存在savedata里面');
                            }
                            that.saveCallback = function () {
                                console.log('回调运行成功了');
                                //console.log(that.line);
                                //console.log(that.times);
                                var obj = that.needData[that.line + '_' + that.times];
                                //console.log(obj);
                                //console.log('回调中该删除的数据如下');
                                //console.log(that[obj.d][obj.uid][obj.win]);
                                //放在这里就能保证是真正战斗的时候才删除了
                                delete that[obj.d][obj.uid][obj.win];
                                delete that.lineTeam[that.line][that.times];
                                that.cleanTeam('left' + line);
                                that.cleanTeam('right' + line);
                                that.atkData[line] = '0';
                                that.defData[line] = '0';
                                that.attackProcess(that.times,that.line);
                                console.log('老回调运行成功！');
                                //这里执行完了一定要设置为空的才可以啊，不然会死循环的
                                that.saveCallback = null;
                            };
                            
                            return false;
                        }
                    } else {
                        console.log('肯定是哪里出错了，一步一步查一下看看');
                    }

                } else {
                    //console.log('继续战斗进程');
                    that.attackProcess(times + 1, line);

                }
            } else {
                //console.log('结束');
                console.log('line' + line + '的战斗全部结束了！');
                that.atkData[line] = '0';
                that.defData[line] = '0';
                that.checkFinish();
            }

        },

        checkFinish : function() {
            var str = '';
            var that = this;
            for(var i = 1;i < 4;i ++) {
                str += this.atkData[i];
                str += this.defData[i];
            }
            if(str == '000000') {
                this.isTimeout.push(setTimeout(function() {
                    if (that.winner != null) {
                        miscpop.battleResultFromGvg(that.winner, that.islast);
                    } else {
                        xGame.loadWorld('gvg');
                    }
                }
                ,1000));
            }
        },

        //战斗线程；返回false说明已经打完了，返回true说明还没有打完
        attackThread : function(data, times, line) {
            var that = this;
            //console.log(data);
            if (that.skip) {
                return false;
            }
            
            //1.队伍上阵(或者更新队伍数据),
            that._fillTeam(line, data);

            if(teamsprites['left' + line] == undefined || teamsprites['right' + line] == undefined){
                console.log('line' + line + '需要等待打完');
                return false;
            }

            var attackFn = function(k, i, data) {
                var k = k || 1;

                var step = data.process[k];

                if (typeof step != 'undefined') {
                    //console.log('第' + i + '排: 第' + k + '步');
                    //三个参数，第一个是行，第二个是自己组建的数据，第三个是行数据，第四个参数用来检测是否是最后一个数据
                    that.attack(i, {
                        left: {
                            hurt: step.hit_left,
                            die: step.dead_left
                        },
                        right: {
                            hurt: step.hit_right,
                            die: step.dead_right
                        }
                    }, data, data.process[k + 1]);

                    that.timeoutFunc = setTimeout(function() {
                        //这段代码可以解决空阵的bug
                        if (k == 1 && (data['left']['matrix'].length == 0 || data['right']['matrix'].length == 0)){
                            that.checkFunc(i, data);
                        }
                        attackFn(k + 1, i, data);
                    },
                    2500);
                    that.isTimeout.push(that.timeoutFunc);
                } else {
                    
                    that.attackProcess(times + 1, line);
                }
            };

            /* 开始进攻过程 */
            attackFn(1, line, data);
        },

        showWin : function(line, d, w_times) {
            var that = this;
            var obj = $('<div class="winflag d_' + d + ' line' + line + ' wint' + w_times + '"></div>');
            obj.appendTo(this.e.find('#battlefield')).unbind('webkitAnimationEnd').one('webkitAnimationEnd',function() {
                obj.remove();
                obj = null;
                //这里不用处理
            });
        },

        /* 补上阵队伍 */
        _fillTeam: function(line, linedata) {
            //左右两边
            var that = this;
            var teams = ['left', 'right'];
            for (var ii = 0; ii < 2; ii++) {
                var direction = teams[ii];
                var mydata = linedata[direction];


                if (typeof teamsprites[direction + line] != 'undefined') { //当战场上当前位置有人
                    //当还是初始状态的时候，重新设置血
                    if (teamsprites[direction + line].uid == mydata.uid && teamsprites[direction + line].blood == 1 && teamsprites[direction + line].allblood == 1) {
                        teamsprites[direction + line].updateBlood({
                            blood: mydata.allblood,
                            allblood: mydata.allblood
                        });
                        continue;
                        //当是自己的时候，修改当前血
                    } else if (teamsprites[direction + line].uid == mydata.uid && typeof mydata.blood != 'undefined') {
                        teamsprites[direction + line].updateBlood({
                            blood: mydata.blood,
                            allblood: mydata.allblood
                        });
                        continue;
                        //当换人的时候，需要重新创建
                    } else if (teamsprites[direction + line].uid != mydata.uid) {
                        this.cleanTeam(direction + line);
                    }
                }

                this._createTeam({
                    direction: direction,
                    line: line,
                    teamid: direction + line,
                    uid: mydata['uid'],
                    name: that[direction + 'Data'][mydata['uid']].nickname,
                    level: 50,
                    blood: mydata['blood'] > 0 ? mydata['blood'] : mydata['allblood'],
                    allblood: mydata['allblood'],
                    matrix: mydata['matrix']
                });
                //console.log('fillTeam: ' + direction + line);
                that.clearLiByUid(direction, mydata['uid']);
                //console.log('上人了');

                if(direction == 'left') {
                    this.atkData[line] = mydata['uid'];
                } else {
                    this.defData[line] = mydata['uid'];
                }
            }

        },

        //根据uid删除li，并且
        clearLiByUid : function(d,uid) {
            //console.log('应该去掉' + d + '中uid=' + uid + '的用户。');
            if (d == 'left') {
                this.leftDom.find('#ui' + uid).remove();
                this.leftData.num -= 1;
                this.spIscroll(1);
            } else {
                this.rightDom.find('#ui' + uid).remove();
                this.rightData.num -= 1;
                this.spIscroll(2);
            }
        },

        //attack(2, {leftteam:{hurt: 300, alldie: 1, die:[]}, rightteam:{hurt: 500, alldie:0, die:[1]}});
        attack: function(line, battledata, data, isLastStep) {
            var that = this;
            
            if (that.skip) {
                return false;
            }
            
            var domA = $('#left' + line);
            var domB = $('#right' + line);
            
            if (domA == undefined || domB == undefined) {
                //console.log('空阵不管');
                return false;
            }
            
            var domA_x = domA.css('left'); //原本的位置
            var domA_mx = '50px'; //移动多少
            var domB_x = domB.css('left');
            var domB_mx = '266px';
            
            var callback = function() {
                domA.animate({left: domA_x}, 50, 'linear', function() {
                    domA = domA_x = domA_mx = null;
                    var teamlist = ['left', 'right'];
                    var nowsprite = null;
                    var nowresult = null;
                    for (var ii = 0; ii < teamlist.length; ii++) {
                        nowsprite = teamsprites[teamlist[ii] + line];
                        nowresult = battledata[teamlist[ii]];
                        nowsprite.showSuffer(nowresult.hurt);
                        nowsprite.die(nowresult.die); //如果全部挂，die也返回空，因为在上面掉血的时候已经整体挂了
                        //console.log(nowresult.die);
                        nowsprite = null;
                        nowresult = null;
                    }
                    //如果是最后一步，再显示连胜
                    if (isLastStep == undefined) {
                        that.checkFunc(line, data);
                    }

                });
                domB.animate({left: domB_x}, 50, 'linear', function() {
                    domB = domB_x = domB_mx = null;
                });

            };

            domA.animate({left: domA_mx}, 200);
            domB.animate({left: domB_mx}, 200, 'linear', function() {
                that.attackAnim(line, callback);
            });

        },

        //非常重要的检测，显示连胜次数的
        checkFunc: function(i, data) {
            var that = this;
            //平局特殊处理，直接下一个进程
            if(data.left_win == 0 && data.right_win == 0) {
                console.log('平局');
                that.atkData[i] = '0';
                that.defData[i] = '0';
                that.showWin(i, 'left', 0);
                that.showWin(i, 'right', 0);
                that.cleanTeam('left' + i);
                that.cleanTeam('right' + i);

                return false;
            }

            var _died;
            if (data.left_win > 0) {
                _died = 'right' + i;
                that.defData[i] = '0';
                if(data.left_win == 6){
                    that.atkData[i] = '0';
                    that.cleanTeam('left' + i);
                }
                that.showWin(i, 'left', data.left_win);

            } else {
                _died = 'left' + i;
                that.atkData[i] = '0';
                if(data.right_win == 6){
                    that.defData[i] = '0';
                    that.cleanTeam('right' + i);
                }
                that.showWin(i, 'right', data.right_win);
            }
        },

        /* 根据参数生成队伍
         * param : data
         * data.teamid : left1
         * data.direction : left/right
         * data.line : 1/2/3
         * data.uid : 1000
         * data.name : ''
         * data.level : 100
         * data.blood : 100
         * data.allblood : 100
         * data.matrix : {}
         */
        _createTeam: function(data) {
            teamsprites[data.teamid] = new Team(data);
            this.e.find('#battlefield').append(teamsprites[data.teamid].e);
        },

        /* 清理队伍 */
        cleanTeam: function(teamid) {
            //清理战场上的尸体
            //console.log('清理队伍：' + teamid);
            if (teamsprites[teamid]) {
                teamsprites[teamid].die(true);
                delete teamsprites[teamid];
            }

        },

        attackAnim: function(line, callback) {
            var _x = 150;
            var _y = 180 * (line - 1);

            var moviestep = 60;
            var movieclip = new movieClipClass({
                "img": staticUrl + 'img/pop/cbattle_page_anim2.png',
                "width": 270,
                "height": 155,
                "x": _x,
                "y": _y,
                "loop": false,
                "endDestory": true,
                "callback": callback,
                "frames": [{
                    rect: [0, 0],
                    interval: moviestep
                },
                {
                    rect: [270, 0],
                    interval: moviestep
                },
                {
                    rect: [540, 0],
                    interval: moviestep
                },
                {
                    rect: [810, 0],
                    interval: moviestep
                },
                {
                    rect: [0, 155],
                    interval: moviestep
                },
                {
                    rect: [270, 155],
                    interval: moviestep
                },
                {
                    rect: [540, 155],
                    interval: moviestep
                },
                {
                    rect: [810, 155],
                    interval: moviestep
                },
                {
                    rect: [0, 0],
                    interval: moviestep
                },
                {
                    rect: [270, 0],
                    interval: moviestep
                },
                {
                    rect: [540, 0],
                    interval: moviestep
                },
                {
                    rect: [810, 0],
                    interval: moviestep
                },
                {
                    rect: [0, 155],
                    interval: moviestep
                },
                {
                    rect: [270, 155],
                    interval: moviestep
                },
                {
                    rect: [540, 155],
                    interval: moviestep
                },
                {
                    rect: [810, 155],
                    interval: moviestep
                },
                ],
            });
            $('#battlefield').append(movieclip.obj);
            movieclip.play();
        },

        refight: function() {
            this.battle_log = null;
            this.winner = null;
            this.callback = null;
            this.callbackData = null;
            this.deleteD = null;
            this.deleteUid = null;
            this.deleteWin = null;
            this.saveCallback = null;
            this.times = null;
            this.line = null;
            this.timeoutFunc = null;
            this.islast = null;
            
            teamsprites = {};
            this.atkData = {};
            this.defData = {};
            this.leftSaveData = {};
            this.rightSaveData = {};
            this.leftHeroWinList = {};
            this.rightHeroWinList = {};
            this.lineTeam = {};
            this.leftData = {};
            this.rightData = {};
            this.needData = {};
            
            this.lineStatus = {};
            this.skip = false;
            this.rightDom.find('#skipbtn').remove();
            this.rightDom.find('#gvgbattle_right').height('460px');
            if (this.rightScroller != null) {
                this.rightScroller.wrapperH = 460;
                this.rightScroller.refresh();
            }
            
            $('div#battlefield').html('');
            this.fight(this.allData);
        },
        
        clear: function() {
            if (this.leftScroller != undefined) {
                this.leftScroller.destroy();
                delete this.leftScroller;
            }

            if (this.leftScroller != undefined) {
                this.leftScroller.destroy();
                delete this.rightScroller;
            }
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            if (this.leftDom) {
                this.leftDom.remove();
                this.leftDom = null;
            }
            if (this.rightDom) {
                this.rightDom.remove();
                this.rightDom = null;
            }
            this.leftData = null;
            this.rightData = null;
            this.atkData = null;
            this.defData = null;
            this.battle_log = null;
            this.winner = null;
            teamsprites = null;

            this.leftSaveData = null;
            this.rightSaveData = null;
            this.leftHeroWinList = null;
            this.rightHeroWinList = null;

            this.callback = null;
            this.callbackData = null;
            
            this.deleteD = null;
            this.deleteUid = null;
            this.deleteWin = null;
            
            this.saveCallback = null;
            this.needData = null;
            this.times = null;
            this.line = null;
            
            this.skip = null;
            this.timeoutFunc = null;
            this.lineTeam = null;
            
            this.allData = null;
            this.islast = null;
            this.lineStatus = null;  
            if (this.e) {
                this.e.undelegate('#skipbtn','click');          
                this.e.find('#battlefield').unbind('webkitAnimationEnd');
                this.e.remove();
                this.e = null;
            }
        }
    };
})();