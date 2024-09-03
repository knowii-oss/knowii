import React, { useState, FormEventHandler } from 'react';
import { useForm, usePage, router } from '@inertiajs/react';
import ActionMessage from '@/Components/ActionMessage';
import ActionSection from '@/Components/ActionSection';
import ConfirmationModal from '@/Components/ConfirmationModal';
import DangerButton from '@/Components/DangerButton';
import DialogModal from '@/Components/DialogModal';
import FormSection from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SectionBorder from '@/Components/SectionBorder';
import TextInput from '@/Components/TextInput';

interface TeamMemberManagerProps {
  team: any;
  availableRoles: Array<any>;
  userPermissions: any;
}

const TeamMemberManager: React.FC<TeamMemberManagerProps> = ({ team, availableRoles, userPermissions }) => {
  const page: any = usePage();

  const [currentlyManagingRole, setCurrentlyManagingRole] = useState(false);
  const [managingRoleFor, setManagingRoleFor] = useState<any>(null);
  const [confirmingLeavingTeam, setConfirmingLeavingTeam] = useState(false);
  const [teamMemberBeingRemoved, setTeamMemberBeingRemoved] = useState<any>(null);

  const addTeamMemberForm = useForm({
    email: '',
    role: null,
  });

  const updateRoleForm = useForm({
    role: null,
  });

  const leaveTeamForm = useForm({});
  const removeTeamMemberForm = useForm({});

  const addTeamMember: FormEventHandler = (e) => {
    e.preventDefault();
    addTeamMemberForm.post(route('team-members.store', team), {
      errorBag: 'addTeamMember',
      preserveScroll: true,
      onSuccess: () => addTeamMemberForm.reset(),
    });
  };

  const cancelTeamInvitation = (invitation: any) => {
    router.delete(route('team-invitations.destroy', invitation), {
      preserveScroll: true,
    });
  };

  const manageRole = (teamMember: any) => {
    setManagingRoleFor(teamMember);
    updateRoleForm.setData('role', teamMember.membership.role);
    setCurrentlyManagingRole(true);
  };

  const updateRole: FormEventHandler = (e) => {
    e.preventDefault();
    updateRoleForm.put(route('team-members.update', [team, managingRoleFor]), {
      preserveScroll: true,
      onSuccess: () => setCurrentlyManagingRole(false),
    });
  };

  const confirmLeavingTeam = () => {
    setConfirmingLeavingTeam(true);
  };

  const leaveTeam: FormEventHandler = (e) => {
    e.preventDefault();
    leaveTeamForm.delete(route('team-members.destroy', [team, page.props.auth.user]));
  };

  const confirmTeamMemberRemoval = (teamMember: any) => {
    setTeamMemberBeingRemoved(teamMember);
  };

  const removeTeamMember: FormEventHandler = (e) => {
    e.preventDefault();
    removeTeamMemberForm.delete(route('team-members.destroy', [team, teamMemberBeingRemoved]), {
      errorBag: 'removeTeamMember',
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => setTeamMemberBeingRemoved(null),
    });
  };

  const displayableRole = (role: string) => {
    return availableRoles.find((r) => r.key === role)?.name;
  };

  return (
    <div>
      {userPermissions.canAddTeamMembers && (
        <>
          <SectionBorder />

          <FormSection
            onSubmit={addTeamMember}
            title="Add Team Member"
            description="Add a new team member to your team, allowing them to collaborate with you."
            actions={
              <>
                <ActionMessage on={addTeamMemberForm.recentlySuccessful} className="me-3">
                  Added.
                </ActionMessage>
                <PrimaryButton className={addTeamMemberForm.processing ? 'opacity-25' : ''} disabled={addTeamMemberForm.processing}>
                  Add
                </PrimaryButton>
              </>
            }
          >
            <div className="col-span-6">
              <div className="max-w-xl text-sm text-gray-600 dark:text-gray-400">
                Please provide the email address of the person you would like to add to this team.
              </div>
            </div>

            <div className="col-span-6 sm:col-span-4">
              <InputLabel htmlFor="email" value="Email" />
              <TextInput
                id="email"
                type="email"
                value={addTeamMemberForm.data.email}
                onChange={(e) => addTeamMemberForm.setData('email', e.target.value)}
                className="block w-full mt-1"
              />
              <InputError message={addTeamMemberForm.errors.email} className="mt-2" />
            </div>

            {availableRoles.length > 0 && (
              <div className="col-span-6 lg:col-span-4">
                <InputLabel htmlFor="roles" value="Role" />
                <InputError message={addTeamMemberForm.errors.role} className="mt-2" />

                <div className="relative z-0 mt-1 border border-gray-200 rounded-lg cursor-pointer dark:border-gray-700">
                  {availableRoles.map((role, i) => (
                    <button
                      key={role.key}
                      type="button"
                      className={`relative px-4 py-3 inline-flex w-full rounded-lg focus:z-10 focus:outline-none focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-600 ${
                        i > 0 ? 'border-t border-gray-200 dark:border-gray-700 focus:border-none rounded-t-none' : ''
                      } ${i !== availableRoles.length - 1 ? 'rounded-b-none' : ''}`}
                      onClick={() => addTeamMemberForm.setData('role', role.key)}
                    >
                      <div className={`${addTeamMemberForm.data.role && addTeamMemberForm.data.role !== role.key ? 'opacity-50' : ''}`}>
                        <div className="flex items-center">
                          <div
                            className={`text-sm text-gray-600 dark:text-gray-400 ${
                              addTeamMemberForm.data.role === role.key ? 'font-semibold' : ''
                            }`}
                          >
                            {role.name}
                          </div>
                          {addTeamMemberForm.data.role === role.key && (
                            <svg
                              className="w-5 h-5 text-green-400 ms-2"
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              strokeWidth="1.5"
                              stroke="currentColor"
                            >
                              <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                              />
                            </svg>
                          )}
                        </div>
                        <div className="mt-2 text-xs text-gray-600 dark:text-gray-400 text-start">{role.description}</div>
                      </div>
                    </button>
                  ))}
                </div>
              </div>
            )}
          </FormSection>
        </>
      )}

      {team.team_invitations.length > 0 && userPermissions.canAddTeamMembers && (
        <>
          <SectionBorder />

          <ActionSection
            title="Pending Team Invitations"
            description="These people have been invited to your team and have been sent an invitation email. They may join the team by accepting the email invitation."
            content={
              <div className="space-y-6">
                {team.team_invitations.map((invitation: any) => (
                  <div key={invitation.id} className="flex items-center justify-between">
                    <div className="text-gray-600 dark:text-gray-400">{invitation.email}</div>
                    {userPermissions.canRemoveTeamMembers && (
                      <button
                        className="text-sm text-red-500 cursor-pointer ms-6 focus:outline-none"
                        onClick={() => cancelTeamInvitation(invitation)}
                      >
                        Cancel
                      </button>
                    )}
                  </div>
                ))}
              </div>
            }
          ></ActionSection>
        </>
      )}

      {team.users.length > 0 && (
        <>
          <SectionBorder />

          <ActionSection
            title="Team Members"
            description="All of the people that are part of this team."
            content={
              <div className="space-y-6">
                {team.users.map((user: any) => (
                  <div key={user.id} className="flex items-center justify-between">
                    <div className="flex items-center">
                      <img className="object-cover w-8 h-8 rounded-full" src={user.profile_photo_url} alt={user.name} />
                      <div className="ms-4 dark:text-white">{user.name}</div>
                    </div>
                    <div className="flex items-center">
                      {userPermissions.canUpdateTeamMembers && availableRoles.length > 0 ? (
                        <button className="text-sm text-gray-400 underline ms-2" onClick={() => manageRole(user)}>
                          {displayableRole(user.membership.role)}
                        </button>
                      ) : (
                        <div className="text-sm text-gray-400 ms-2">{displayableRole(user.membership.role)}</div>
                      )}
                      {page.props.auth.user.id === user.id ? (
                        <button className="text-sm text-red-500 cursor-pointer ms-6" onClick={confirmLeavingTeam}>
                          Leave
                        </button>
                      ) : (
                        userPermissions.canRemoveTeamMembers && (
                          <button className="text-sm text-red-500 cursor-pointer ms-6" onClick={() => confirmTeamMemberRemoval(user)}>
                            Remove
                          </button>
                        )
                      )}
                    </div>
                  </div>
                ))}
              </div>
            }
          ></ActionSection>
        </>
      )}

      <DialogModal
        show={currentlyManagingRole}
        onClose={() => setCurrentlyManagingRole(false)}
        title="Manage Role"
        content={
          managingRoleFor && (
            <div className="relative z-0 mt-1 border border-gray-200 rounded-lg cursor-pointer dark:border-gray-700">
              {availableRoles.map((role, i) => (
                <button
                  key={role.key}
                  type="button"
                  className={`relative px-4 py-3 inline-flex w-full rounded-lg focus:z-10 focus:outline-none focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-600 ${
                    i > 0 ? 'border-t border-gray-200 dark:border-gray-700 focus:border-none rounded-t-none' : ''
                  } ${i !== availableRoles.length - 1 ? 'rounded-b-none' : ''}`}
                  onClick={() => updateRoleForm.setData('role', role.key)}
                >
                  <div className={`${updateRoleForm.data.role && updateRoleForm.data.role !== role.key ? 'opacity-50' : ''}`}>
                    <div className="flex items-center">
                      <div
                        className={`text-sm text-gray-600 dark:text-gray-400 ${
                          updateRoleForm.data.role === role.key ? 'font-semibold' : ''
                        }`}
                      >
                        {role.name}
                      </div>
                      {updateRoleForm.data.role === role.key && (
                        <svg
                          className="w-5 h-5 text-green-400 ms-2"
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          strokeWidth="1.5"
                          stroke="currentColor"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                          />
                        </svg>
                      )}
                    </div>
                    <div className="mt-2 text-xs text-gray-600 dark:text-gray-400">{role.description}</div>
                  </div>
                </button>
              ))}
            </div>
          )
        }
        footer={
          <>
            <SecondaryButton onClick={() => setCurrentlyManagingRole(false)}>Cancel</SecondaryButton>
            <PrimaryButton
              className={updateRoleForm.processing ? 'ms-3 opacity-25' : 'ms-3'}
              disabled={updateRoleForm.processing}
              onClick={updateRole}
            >
              Save
            </PrimaryButton>
          </>
        }
      />

      <ConfirmationModal
        show={confirmingLeavingTeam}
        onClose={() => setConfirmingLeavingTeam(false)}
        title="Leave Team"
        content="Are you sure you would like to leave this team?"
        footer={
          <>
            <SecondaryButton onClick={() => setConfirmingLeavingTeam(false)}>Cancel</SecondaryButton>
            <DangerButton
              className={leaveTeamForm.processing ? 'ms-3 opacity-25' : 'ms-3'}
              disabled={leaveTeamForm.processing}
              onClick={leaveTeam}
            >
              Leave
            </DangerButton>
          </>
        }
      />

      <ConfirmationModal
        show={!!teamMemberBeingRemoved}
        onClose={() => setTeamMemberBeingRemoved(null)}
        title="Remove Team Member"
        content="Are you sure you would like to remove this person from the team?"
        footer={
          <>
            <SecondaryButton onClick={() => setTeamMemberBeingRemoved(null)}>Cancel</SecondaryButton>
            <DangerButton
              className={removeTeamMemberForm.processing ? 'ms-3 opacity-25' : 'ms-3'}
              disabled={removeTeamMemberForm.processing}
              onClick={removeTeamMember}
            >
              Remove
            </DangerButton>
          </>
        }
      />
    </div>
  );
};

export default TeamMemberManager;
