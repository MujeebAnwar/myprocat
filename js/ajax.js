export { AJAX }
import { logger } from '/js/logger.js'
class AJAX extends logger
{
    constructor() {
        super();
        this._timers = 0;
        this.currentTimer = {};
        this._stopFlags = {};
        this._failureHandler = () => { };
    }
	wantStopPoll(timerId)
	{
		return this._stopFlags[timerId];
    }
	ajaxReq(url,completeCallback,postData)
	{
		var req = new XMLHttpRequest();

		req.onreadystatechange = 
		() => {
			if (req.readyState === XMLHttpRequest.DONE) 
			{
                if (req.status >= 200 && req.status < 300) {
                    completeCallback(req.responseText)
                } else {
                    this._failureHandler();
                }
			}
		};
		req.withCredentials = true;
        req.open('POST', url, true);
        req.timeout = 15000;
		if(postData !== null && postData !== undefined)
		{
			let pd = postData;
			if(!(postData instanceof FormData))
			{
				pd = this.toFormData(postData);
			}
			req.send(pd);
		} else {
			req.send();
		}
	}
    ajaxPoll(msElapse, url, completeCallback, postData, readyCallback = () => { return true; })
	{
		this._timers++;
        let myTimer = this._timers;
        let myAjax = this;
        var loop = function(fast)
        {
            let timeout = msElapse;
            if (fast) {
                timeout = 100;
            }
            if (readyCallback()) {
                myAjax.currentTimer[myTimer] = setTimeout(
                    function () {
                        myAjax.ajaxReq(url,
                            function (response) {
                                let repollfast = completeCallback(response);
                                if (!myAjax.wantStopPoll(myTimer)) {
                                    loop(repollfast);
                                }
                            },
                            postData
                        );
                    }
                    , timeout);
            } else {
                myAjax.currentTimer[myTimer] = setTimeout(loop(true), timeout);
            }
		};
        loop.bind(this, myAjax, myTimer, postData, readyCallback);

        this._failureHandler = () => {
            setTimeout(loop, msElapse);
        };
		loop(true);
		return this._timers;
	}
	cancelPoll(timerId)
	{
		this._stopFlags[timerId] = true;
		clearTimeout(this.currentTimer[timerId]);
	}
	jsonReq(url,jsonCallback,postData)
	{
		this.ajaxReq(url,
			(responseText) => {
				let resp = {};
				try {
					resp = JSON.parse(responseText); 
				} catch (e)
				{
					this.log('Parse',e.message+"\n\n"+responseText);
					resp = {invalid_json:responseText};
				}
				jsonCallback(resp);
			},postData);
	}
    jsonPoll(msElapse, url, completeCallback, postData, readyCallback = () => { return true; })
	{
		this.ajaxPoll(
			msElapse,url,
			(response) => {
				let resp = {};
				try {
					resp = JSON.parse(response); 
				} catch (e)
				{
                    this.log('Parse', e.message + "\n\n" + responseText);
					resp = {invalid_json:response};
				}
				return completeCallback(resp);
			},
            postData,
            readyCallback);
	}
	toFormData(aThing)
	{
		var formData = new FormData();
		var assocArray = aThing;
		if(typeof assocArray !== 'object')
		{
			// Wrap non-objects in an object where the implicit property name is "val"
			assocArray = {val:aThing};
		}
		for (const property in assocArray) 
		{
			var propValue = assocArray[property];
			if(typeof propValue !== 'string')
			{
				propValue = JSON.stringify(assocArray[property]);
			}
			formData.append(property, propValue);
		}
		return formData;
	}
}