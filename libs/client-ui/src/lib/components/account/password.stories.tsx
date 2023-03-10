import type { ComponentStory, ComponentMeta } from '@storybook/react';
import Password from './password';

const Story: ComponentMeta<typeof Password> = {
  component: Password,
  title: 'Password',
};
export default Story;

const Template: ComponentStory<typeof Password> = (args) => <Password {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
