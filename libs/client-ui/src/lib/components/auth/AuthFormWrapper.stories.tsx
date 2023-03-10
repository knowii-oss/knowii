import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { AuthFormWrapper } from './AuthFormWrapper';

const Story: ComponentMeta<typeof AuthFormWrapper> = {
  component: AuthFormWrapper,
  title: 'AuthFormWrapper',
};
export default Story;

const Template: ComponentStory<typeof AuthFormWrapper> = (args) => <AuthFormWrapper {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
