'use strict';

/**
 * Helper functions.
 * Mainly used for the build system
 */
const path = require('path');

/**
 * Path to the root of the workspace
 */
const _root = path.resolve(process.cwd(), '.'); // project root folder

/**
 * Function that returns the relative path to the given file, from the root of the workspace
 * Pass in a path from anywhere in the project and get the path to that file, relative to the workspace root
 */
const root = path.join.bind(path, _root);

const hasProcessFlag = (flag) => {
  return process.argv.join('').indexOf(flag) > -1;
};

exports.root = root;
exports.hasProcessFlag = hasProcessFlag;
