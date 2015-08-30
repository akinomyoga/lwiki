#!/bin/bash

function mkd {
  local -a directories
  directories=()
  while (($#)); do
    [[ ! -d $1 ]] && directories[${#directories[@]}]="$1"
    shift
  done
  ((${#directories[@]})) && mkdir -p "${directories[@]}"
}

function mkdf {
  local -a directories
  directories=()
  while (($#)); do
    [[ $1 == */* && ! -d ${1%/*} ]] && directories[${#directories[@]}]="${1%/*}"
    shift
  done
  ((${#directories[@]})) && mkdir -p "${directories[@]}"
}

function install.file {
  local opt_prefix=
  local opt_error=
  local -a files
  files=()
  while (($#)); do
    local arg="$1"
    case "$arg" in
    (--prefix=?*)
      opt_prefix="${arg#*=}" ;;
    (-*)
      opt_error=1
      echo "${0##*/}: unknown option \`$arg'." >&2 ;;
    (*)
      files[${#files[@]}]="$arg"
    esac
    shift
  done

  local op_type
  if [[ $opt_prefix ]]; then
    op_type=prefix
  elif ((${#files[@]}==2)); then
    op_type=rename
  elif ((${#files[@]}>2)) && [[ -d ${files[${#files}-1]} ]]; then
    op_prefix="${files[${#files[@]}-1]}"
    files="${files[@]:0:${#files[@]}-1}"
    op_type=prefix
  else
    echo "${0##*/}: unknown form of the arguments." >&2
    echo "usage: ${0##*/} FILE1 FILE2" >&2
    echo "usage: ${0##*/} FILES... DIR" >&2
    echo "usage: ${0##*/} --prefix=DIR FILES..." >&2
    opt_error=1
  fi

  [[ $opt_error ]] && return 1

  case "$op_type" in
  (prefix)
    local dst src
    for src in "${files[@]}"; do
      dst="$opt_prefix/$src"
      [[ ! -f $dst || $dst -ot $src ]] || continue
      mkdf "$dst"
      cp -pv "$src" "$dst"
    done ;;
  (rename)
    local src="${files[0]}" dst="${files[1]}"
    [[ -d $dst ]] && dst="$dst/$src"
    if [[ ! -f $dst || $dst -ot $src ]]; then
      mkdf "$dst"
      cp -pv "$src" "$dst"
    fi ;;
  esac
}

function command.install {
  local prefix="${PREFIX:-$HOME/.mwg}"

  local share="$prefix/share/lwiki"
  local bin="$prefix/bin"
  mkd "$share" "$bin"

  install.file --prefix="$share" \
               index.php \
               lib/cmd.comment-add.php \
               lib/lib.flock.php \
               lib/lib.hist.php \
               lib/lib.ldiff.php \
               lib/lib.lwiki.php \
               lib/lib.page.php \
               lib/lib.page-edit.php \
               lib/page.diff.php \
               lib/page.edit.php \
               lib/page.hist.php \
               lib/page.list.php \
               lib/page.read.php \
               lib/stub.comment-form.php \
               lib/stub.menu.php \
               res/lwiki.js \
               res/lwiki.css \
               lwiki \
               lwiki-convert \
               lwiki_config.php

  ln -vfs "$share"/lwiki "$bin"/lwiki
}

command.install
