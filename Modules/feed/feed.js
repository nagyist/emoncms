
var feed = {

    apikey: false,
    public_userid: 0,
    public_username: "",
    
    apikeystr: function() {
        if (feed.apikey) {
            return "?apikey="+feed.apikey;
        } else {
            return "";
        }
    },
    
    public_username_str: function() {
        if (feed.public_userid) {
            return public_username+"/";
        } else {
            return "";
        }    
    },
    
    create: function(tag, name, engine, options, unit){
        var result = {};
        var data = {
            tag: tag,
            name: name,
            engine: engine,
            options: JSON.stringify(options),
            unit: unit || ''
        }
        $.ajax({ url: path+"feed/create.json", data: data, dataType: 'json', async: false, success: function(data){result = data;} });
        return result;
    },

    // Returns a list of feeds
    // If callback is provided, it will be called with the feeds data
    // If no callback is provided, it will return the feeds data synchronously
    list: function(callback=null)
    {   
        var feeds = null;
        $.ajax({                                      
            url: path+this.public_username_str()+"feed/list.json"+this.apikeystr(),
            dataType: 'json',
            cache: false,
            async: (typeof callback === "function"),                      
            success: function(result) {
                feeds = result; 
                if (!result || result===null || result==="" || result.constructor!=Array) {
                    console.log("ERROR","feed.list invalid response: "+result);
                    feeds = null;
                }

                if (typeof callback === "function") {
                    callback(feeds);
                }
            }
        });
        
        return feeds;
    },

    // Returns an object with feeds grouped by tag
    by_tag: function(feeds) {
        if (!Array.isArray(feeds) || feeds.length === 0) return {};
        const bytag = {};
        for (const feed of feeds) {
            if (!feed || !feed.tag) continue;
            if (bytag[feed.tag]) {
                bytag[feed.tag].push(feed);
            } else {
                bytag[feed.tag] = [feed];
            }
        }
        return bytag;
    },

    // Returns an object with feeds grouped by group
    by_id: function(feeds) {
        if (!Array.isArray(feeds) || feeds.length === 0) return {};
        const byid = {};
        for (const feed of feeds) {
            if (!feed || feed.id == null) continue;
            byid[String(feed.id)] = feed;
        }
        return byid;
    },
    
    // Alternative method to get feeds by ID (backward compatibility)
    listbyid: function() {
        var feeds = feed.list();
        if (feeds === null) { return null; }
        var byid = {};
        for (z in feeds) byid[feeds[z].id] = feeds[z];
        return byid;
    },

    // Asynchronous version of listbyid (backward compatibility)
    listbyidasync: function(f)
    {   
        var feeds = null;
        $.ajax({                                      
            url: path+this.public_username_str()+"feed/list.json"+this.apikeystr(),
            dataType: 'json',
            async: true,                      
            success: function(result) {
                feeds = result; 
                if (!result || result===null || result==="" || result.constructor!=Array) {
                    console.log("ERROR","feed.listbyidasync invalid response: "+result);
                    f(null);
                    return;
                }
                
                var byid = {};
                for (z in feeds) byid[feeds[z].id] = feeds[z];
                f(byid);
            } 
        });
    },

    set_fields: function(id, fields){
        $.ajax({ url: path+"feed/set.json", data: {id:id,fields:JSON.stringify(fields)}, success: function(result) {
            if (result.success!=undefined && !result.success) {
                alert(result.message);
            }
        }});
    },

    remove: function(id){
        $.ajax({ url: path+"feed/delete.json", data: {id:id}, async: false, success: function(data){
             // Clean processlists of deleted feeds
             $.ajax({ url: path+"input/cleanprocesslistfeeds.json", async: true, success: function(data){} });
        }});
    },

    clear: function(id){
        let response = false;
        let data = {
            id: id
        }
        $.ajax({ url: path+"feed/clear.json", data: data, async: false, success: function(data){ response = data} });
        return response;
    },
    
    trim: function(id,start_time){
        let response = false;
        let data = {
            id: id,
            start_time: start_time
        }
        $.ajax({ url: path+"feed/trim.json", data: data, async: false, success: function(data){ response = data} });
        return response;
    },

    getvalue: function(feedid,time,callback=false,context=false) {
        let data = {
            id: feedid,
            time: time
        }
        if (feed.apikey) data.apikey = feed.apikey;

        var async = false;
        if ( typeof callback === "function" ) {
            async = true;
        }
       
        var non_async_result = false;
        var ajaxAsyncXdr = $.ajax({ 
            url: path+"feed/value.json", 
            data: data, 
            async: async, 
            success: function(result) {
                if (result===null || result==="") {
                    console.log("ERROR","feed.getvalue invalid response: "+result);
                    result = false;
                } else {
                    non_async_result = result;
                }
                
                if (async) {
                    if (!context) {
                        callback(result);
                    } else {
                        callback(context,result);
                    }
                }
            }
        });
        if (async) {
            return ajaxAsyncXdr;
        } else {
            return non_async_result;
        }
    },

    getdata: function(feedid,start,end,interval,average=0,delta=0,skipmissing=0,limitinterval=0,callback=false,context=false,timeformat='unixms'){
        let data = {
            id: feedid,
            start: start,
            end: end,
            interval: interval,
            average:average,
            delta:delta,
            skipmissing: skipmissing,
            limitinterval: limitinterval,
            timeformat: timeformat
        };
        
        if (typeof feedid === "object") {
            delete data['id'];
            data['ids'] = feedid.join(",");
        }
        
        if (feed.apikey) data.apikey = feed.apikey;

        var async = false;
        if ( typeof callback === "function" ) {
            async = true;
        }

        var non_async_result = false;
        var ajaxAsyncXdr = $.ajax({
            url: path+'feed/data.json',
            data: data,
            dataType: 'json',
            async: async,
            success: function(result) {
                if (!result || result===null || result==="" || result.constructor!=Array) {
                    console.log("ERROR","feed.getdata invalid response: "+result);
                } else {
                    if (timeformat=="notime") {
                        if (data.ids!=undefined) {
                            for (var i in result) {
                                result[i].data = feed.populate_timestamps(result[i].data, start, interval);
                            }
                            non_async_result = result;
                        } else {
                            non_async_result = feed.populate_timestamps(result, start, interval);
                        }
                    } else {
                        non_async_result = result;
                    }
                }
                
                if (async) {
                    if (!context) {
                        callback(result);
                    } else {
                        callback(context,result);
                    }
                }
            }
        });
        if (async) {
            return ajaxAsyncXdr;
        } else {
            return non_async_result;
        }
    },
    
    
    
    getdataDMY_time_of_use: function(id,start,end,interval,split)
    {
        let data = {
            id: id,
            start: start,
            end: end,
            interval: interval,
            split:split
        };
        if (feed.apikey) data.apikey = feed.apikey;
        
        var feed_data = [];
        $.ajax({                                      
            url: path+"feed/data.json",                         
            data: data,
            dataType: 'json',
            async: false,                      
            success: function(result) {
                if (!result || result===null || result==="" || result.constructor!=Array) {
                    console.log("ERROR","feed.getdataDMY_time_of_use invalid response: "+result);
                }
                feed_data = result; 
            }
        });
        return feed_data;
    },

    // Virtual feed process
    set_process: function(feedid,processlist){
        let json_processlist = JSON.stringify(processlist);
        var result = {};
        $.ajax({ url: path+"feed/process/set.json?id="+feedid, method: "POST", data: "processlist="+json_processlist, async: false, success: function(data){result = data;} });
        return result;
    },

    get_process: function(feedid){
        var result = {};
        $.ajax({ url: path+"feed/process/get.json", data: "id="+feedid, async: false, dataType: 'json', success: function(data){result = data;} });
        var processlist = [];
        if (result!="")
        {
            var tmp = result.split(",");
            for (n in tmp)
            {
                var process = tmp[n].split(":"); 
                processlist.push(process);
            }
        }
        return processlist;
    },

    reset_processlist: function(feedid,processid){
        var result = {};
        $.ajax({ url: path+"feed/process/reset.json", data: "id="+feedid, async: false, success: function(data){result = data;} });
        return result;
    },
    
    getmeta: function(feedid)
    {
        let data = {
          id: feedid
        };
        if (feed.apikey) data.apikey = feed.apikey;
        
        var meta = {};
        $.ajax({                                      
            url: path+'feed/getmeta.json',
            data: data,
            dataType: 'json',
            async: false,
            success: function(result) {
                if (!result || result===null || result==="" || result.constructor!=Object) {
                    console.log("ERROR","feed.getmeta invalid response: "+result);
                }
                meta = result; 
            } 
        });
        return meta;
    },
    
    populate_timestamps: function(values, start, interval) {
        var intervalms = interval*1000;
        var time = Math.floor(start/intervalms)*intervalms;
        var with_time = [];
        for (var z in values) {
            with_time.push([time,values[z]]);
            time += intervalms;
        }
        return with_time;
    } 
}

