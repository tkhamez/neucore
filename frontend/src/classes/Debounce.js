/**
 * Returns a debounced version of the given function that only executes once the
 * given delay has passed without a further call. The arguments of the last call
 * are used.
 *
 * @param {Function} fn The function to debounce.
 * @param {number} wait The delay in ms to wait for the last call before executing.
 * @returns {Function} The debounced function.
 */
export function debounce(fn, wait) {
    let timer = null;
    return function (...args) {
        if (timer !== null) {
            clearTimeout(timer);
        }
        timer = setTimeout(() => {
            timer = null;
            fn.apply(this, args);
        }, wait);
    };
}
