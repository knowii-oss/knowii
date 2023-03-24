import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { ClientFormModal } from './client-form-modal';

const Story: ComponentMeta<typeof ClientFormModal> = {
  component: ClientFormModal,
  title: 'ClientFormModal',
};
export default Story;

const Template: ComponentStory<typeof ClientFormModal> = (args) => <ClientFormModal {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
