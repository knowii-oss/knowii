# TODO

<div className="flex flex-row justify-end">
          <Button
            className="w-32 h-16"
            aria-label={'Log out'}
            severity="primary"
          >
          <FaSignOutAlt/>
          </Button>
          </div>,

        router.visit(LOGOUT_URL,{
          method: 'post',
        });
