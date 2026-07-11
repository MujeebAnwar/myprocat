export { logger };
class logger {
    constructor(otherlogger = null) {
        this._loggers = {};
        this._loggers['dbg'] = (msg) => console.log(msg);
    }
    log(type, msg) {
        if (type in this._loggers) {
            this._loggers[type](msg);
        } else {
            this.dbg(type + ': ' + msg);
        }
    }
    dbg(msg) {
        this.log('dbg', msg);
    }
    bindLogger(type, callback) {
        this._loggers[type] = callback;
    }
}
