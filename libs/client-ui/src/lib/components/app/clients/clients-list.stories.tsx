import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { ClientsList } from './clients-list';

const Story: ComponentMeta<typeof ClientsList> = {
  component: ClientsList,
  title: 'ClientsList',
};
export default Story;

const Template: ComponentStory<typeof ClientsList> = (args) => <ClientsList {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
