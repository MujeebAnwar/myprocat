<?php
require_once 'config.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
class cloudbanner extends content_block
{
	public function __construct()
	{
	$myjScript = <<<'CLOUDSCRIPT'
function rn(from, to) {
  return ~~(Math.random() * (to - from + 1)) + from;
}

function rs() {
  return arguments[rn(1, arguments.length) - 1];
}
var cloudColors = ['#7FB5E3', '#9EC9E9','#FFFFFE', '#7FB5E3', '#9EC9E9','#BDCEDE', '#F4F8F9'];
var rndRanges = [[-200,800],[1,200],[20,150],[10,150],[0,cloudColors.length-1]];
const cloud = document.querySelector('#cloud');
var curClouds = null;
var nShadows = 50;
function rnr(rangeNum)
{
	return rn(rndRanges[rangeNum][0],rndRanges[rangeNum][1]);
}
function col2Css(pick)
{
if(cloudColors[pick] === undefined)
{
	console.log("Invalid color pick: "+pick);
}
	return cloudColors[pick];
}
function rndShadDef()
{
	return [rnr(0), rnr(1),rnr(2),rnr(3),rnr(4)];
}
function shad2Css(shad)
{
	return shad[0]+'px '+shad[1]+'px '+shad[2]+'px '+shad[3]+`px
	`+col2Css(shad[4])+`
	`;
}
function boxShadows(max) {
  let ret = [];
  for (let i = 0; i < max; ++i) {
    ret.push(rndShadDef());
  }
  return ret;
}
function rndPcgChg(val,rngNum)
{
	rngSize = rndRanges[rngNum][1] - rndRanges[rngNum][0];
	maxshift = rngSize/20;
	shift = rn(0,maxshift);
	shift -= ~~(maxshift/2);
	if(shift == 0) return val;
	let newval = val+shift;
	while(newval < rndRanges[rngNum][0] || newval > rndRanges[rngNum][1])
	{
		shift = rn(0,maxshift);
		shift -= ~~(maxshift/2);
		if(shift == 0) return val;
		let newval = val+shift;
	}
	return newval;
}


function cloudCreate() {
  let pxWidth = document.getElementById('cloudcontainer').clientWidth;
  let pxHeight = document.getElementById('cloudcontainer').clientHeight;
  rndRanges[0][0] = -200;
  rndRanges[0][1] = pxWidth+200;
  rndRanges[1][1] = -(pxHeight);
  rndRanges[1][0] = -(pxHeight)+50;
  rndRanges[3][1] = pxHeight;

  nShadows = ~~(pxWidth/50);
  curClouds = boxShadows(nShadows);
  cloud.style.boxShadow = def2css(curClouds); 
}
function morphClouds()
{
	if(curClouds && defer === null)
	{
		let len = curClouds.length;
		let rv = [];
		let newDef = [];
		for(let i = 0;i<len;i++)
		{
			let thisDef = curClouds[i];
			if(thisDef[0] < rndRanges[0][1]+180)
			{
				thisDef[0] += 10;
			} else {
				let diff = thisDef[0] - (rndRanges[0][1]+200);
				thisDef[0] = -200+diff;
			}
			newDef.push(thisDef);

		}
		curClouds = newDef;
		cloud.style.boxShadow = def2css(curClouds);
	}

}
function def2css(cloudDef)
{
	let len = cloudDef.length;
	let rv = [];
	for(let i = 0;i<len;i++)
	{
		rv.push(shad2Css(cloudDef[i]));
	}
	return rv.join(',');
}
var defer = null;
function deferCloudCreate()
{
if(defer)
{
	clearTimeout(defer);
}
defer = setTimeout(() => {defer = null;cloudCreate()},1000);
	
}
window.addEventListener('load', cloudCreate); 
window.addEventListener('resize',deferCloudCreate);
document.getElementById('cloudcontainer').addEventListener('click', deferCloudCreate); 
setInterval(morphClouds,10000);
CLOUDSCRIPT
;
	$myCss = <<<CSS

#cloudcontainer
{
  margin:0px;
  width:100%;
  height:calc(60px + 12vh);
}
#cloudcontainer:before
{
    content: 'CasePad Cloud';
    font-size: 10vh;
    color: rgba(0, 0, 0, 1);
    position: relative;
    height: 100%;
    text-align: center;
    z-index: 100;
    font-family: "Tahoma","Verdana",sans-serif;
    font-weight: bold;
    font-style: italic;
    top: 0px;
    display: block;
	text-shadow: 0px 0px 4px white;
	line-height: 2;
	margin-top: 30px;
    overflow: hidden;
}
#cloud {
  overflow: hidden;
  width: 1px; height: 1px;
  transform: translate(-100%, -100%);
  border-radius: 50%;
  filter: url(#filter);
}
CSS
	;
	$svgfilter = <<<HTML
<svg width="0">
  <filter id="filter">
    <feTurbulence type="fractalNoise"
      baseFrequency=".02" numOctaves="8" />
    <feDisplacementMap
      in="SourceGraphic" scale="150" />
  </filter>
</svg>
HTML
	;
	$cloud = new content_block($svgfilter,'div',['id'=>'cloud']);
	$cloud->push(new content_block($myjScript,'script'));
	$cloud->push(new content_block($myCss,'style'));

	parent::__construct($cloud,'div',array("id" => 'cloudcontainer'));
	}
}
?>