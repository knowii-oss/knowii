

          {jetstream.canManageTwoFactorAuthentication && (
            <>
              <div className="mt-10 sm:mt-0">
                <TwoFactorAuthenticationForm requiresConfirmation={confirmsTwoFactorAuthentication} />
              </div>
              <SectionBorder />
            </>
          )}

          <div className="mt-10 sm:mt-0">
            <LogoutOtherBrowserSessionsForm sessions={sessions} />
          </div>

          {jetstream.hasAccountDeletionFeatures && (
            <>
              <SectionBorder />
              <DeleteUserForm className="mt-10 sm:mt-0" />
            </>
          )}
        </div>
      </div>
    </AppLayout>
  );
};

export default Profile;
