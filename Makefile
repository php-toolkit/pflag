# link https://github.com/humbug/box/blob/master/Makefile
#SHELL = /bin/sh
# 每行命令之前必须有一个tab键。如果想用其他键，可以用内置变量.RECIPEPREFIX 声明
# mac 下这条声明 没起作用 !!
.RECIPEPREFIX = >
.PHONY: all usage help clean

# 需要注意的是，每行命令在一个单独的shell中执行。这些Shell之间没有继承关系。
# - 解决办法是将两行命令写在一行，中间用分号分隔。
# - 或者在换行符前加反斜杠转义 \

# 接收命令行传入参数 make COMMAND tag=v2.0.4
# TAG=$(tag) # 使用 $(TAG)

# 定义变量
#SHELL := /bin/bash

# Full build flags used when building binaries. Not used for test compilation/execution.
#BUILDFLAGS :=  -ldflags \
#  " -X $(ROOT_PACKAGE)/pkg/cmd/version.Version=$(VERSION)

# if 条件
#ifdef DEBUG
#BUILDFLAGS := -gcflags "all=-N -l" $(BUILDFLAGS)
#endif

.DEFAULT_GOAL := help
help:
	@echo "There some make command for the project\n"
	@echo "Available Commands:"
	@grep -h -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

clean:  ## Clean all created artifacts
	git clean --exclude=.idea/ -fdx

cs-fix:  ## Fix code style for all files
	gofmt -w ./

cs-diff:   ## Display code style error files
	gofmt -l ./
